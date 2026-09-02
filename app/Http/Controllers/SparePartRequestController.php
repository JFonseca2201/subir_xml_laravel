<?php

namespace App\Http\Controllers;

use App\Models\SparePartRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SparePartRequestController extends Controller
{
    /**
     * Get unique categories from spare parts and product configuration.
     */
    public function categories()
    {
        // Obtener exclusivamente las categorías activas registradas en el sistema
        $categories = \App\Models\Config\ProductCategorie::where('state', 1)
            ->orderBy('title', 'asc')
            ->pluck('title')
            ->map(function ($title) {
                return strtoupper(trim($title));
            })
            ->filter()
            ->unique()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Display a listing of the resource with filters and pagination.
     */
    public function index(Request $request)
    {
        $query = SparePartRequest::query()->with('user');

        // Filtro por palabra clave (Concepto, Marca, Modelo, Año, Detalle de repuesto, Categoría o Precio)
        if ($request->filled('search')) {
            $search = trim($request->get('search'));
            $words = array_filter(explode(' ', $search));
            
            $query->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $cleanWord = str_replace(['$', ','], ['', '.'], $word);
                    $q->where(function ($subQ) use ($word, $cleanWord) {
                        $subQ->where('brand', 'like', "%{$word}%")
                             ->orWhere('model', 'like', "%{$word}%")
                             ->orWhere('year', 'like', "%{$word}%")
                             ->orWhere('items', 'like', "%{$word}%");

                        if (is_numeric($cleanWord)) {
                            $subQ->orWhere('items', 'like', "%\"public_price\":{$cleanWord}%")
                                 ->orWhere('items', 'like', "%\"public_price\": {$cleanWord}%")
                                 ->orWhere('items', 'like', "%\"purchase_price\":{$cleanWord}%")
                                 ->orWhere('items', 'like', "%\"purchase_price\": {$cleanWord}%");
                        }
                    });
                }
            });
        }

        // Filtro por Tracción
        if ($request->filled('traction') && $request->get('traction') !== 'ALL') {
            $query->where('traction', $request->get('traction'));
        }

        // Filtro por Año (soporta búsqueda exacta o parcial conforme escribe)
        if ($request->filled('year')) {
            $query->where('year', 'like', $request->get('year') . '%');
        }

        // Filtro por Categoría
        if ($request->filled('category') && $request->get('category') !== 'ALL') {
            $category = trim($request->get('category'));
            $query->where(function ($q) use ($category) {
                $q->whereRaw("JSON_SEARCH(LOWER(items), 'one', ?, null, '$[*].category') IS NOT NULL", ['%' . strtolower($category) . '%'])
                  ->orWhereRaw("LOWER(items) LIKE ?", ['%"category":"%' . strtolower($category) . '%"%'])
                  ->orWhereRaw("LOWER(items) LIKE ?", ['%"category": "%' . strtolower($category) . '%"%']);
            });
        }

        // Filtro por Precio (min_price y/o max_price)
        $hasMinPrice = $request->filled('min_price') && is_numeric($request->get('min_price'));
        $hasMaxPrice = $request->filled('max_price') && is_numeric($request->get('max_price'));

        if ($hasMinPrice || $hasMaxPrice) {
            $minPrice = $hasMinPrice ? floatval($request->get('min_price')) : null;
            $maxPrice = $hasMaxPrice ? floatval($request->get('max_price')) : null;

            // Secuencia portátil de 0 a 30 para inspeccionar items en JSON
            $numbers = '0 as seq UNION ALL SELECT ' . implode(' UNION ALL SELECT ', array_map(fn($i) => (string)$i, range(1, 30)));
            $query->whereIn('id', function ($sub) use ($numbers, $minPrice, $maxPrice) {
                $sub->select('s.id')
                    ->from('spare_part_requests as s')
                    ->join(\Illuminate\Support\Facades\DB::raw("(SELECT $numbers) as seq"), function ($join) {
                        $join->on('seq.seq', '<', \Illuminate\Support\Facades\DB::raw('JSON_LENGTH(s.items)'));
                    });

                if ($minPrice !== null && $maxPrice !== null) {
                    $sub->whereBetween(\Illuminate\Support\Facades\DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(s.items, CONCAT('$[', seq.seq, '].public_price'))) AS DECIMAL(10,2))"), [$minPrice, $maxPrice]);
                } elseif ($minPrice !== null) {
                    $sub->where(\Illuminate\Support\Facades\DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(s.items, CONCAT('$[', seq.seq, '].public_price'))) AS DECIMAL(10,2))"), '>=', $minPrice);
                } elseif ($maxPrice !== null) {
                    $sub->where(\Illuminate\Support\Facades\DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(s.items, CONCAT('$[', seq.seq, '].public_price'))) AS DECIMAL(10,2))"), '<=', $maxPrice);
                }
            });
        }

        $per_page = $request->get('per_page', 10);
        $requests = $query->orderBy('id', 'desc')->paginate($per_page);

        return response()->json($requests);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 5),
            'traction' => 'nullable|string|max:100',
            'origin_country' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.spare_parts_detail' => 'required|string',
            'items.*.spare_part_brand' => 'required|string|max:255',
            'items.*.category' => 'required|string|max:255',
            'items.*.purchase_price' => 'required|numeric|min:0',
            'items.*.public_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada no válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['user_id'] = auth()->user()->id;

        // Autocapitalizar y recortar espacios
        $data['brand'] = strtoupper(trim($data['brand']));
        $data['model'] = strtoupper(trim($data['model']));
        if (!empty($data['traction'])) {
            $data['traction'] = strtoupper(trim($data['traction']));
        }
        if (!empty($data['origin_country'])) {
            $data['origin_country'] = strtoupper(trim($data['origin_country']));
        }

        // Capitalizar items
        $items = [];
        foreach ($data['items'] as $item) {
            $items[] = [
                'spare_parts_detail' => trim($item['spare_parts_detail']),
                'spare_part_brand' => strtoupper(trim($item['spare_part_brand'])),
                'category' => strtoupper(trim($item['category'])),
                'purchase_price' => floatval($item['purchase_price']),
                'public_price' => floatval($item['public_price']),
            ];
        }
        $data['items'] = $items;

        $sparePartRequest = SparePartRequest::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Búsqueda de repuesto registrada con éxito.',
            'data' => $sparePartRequest->load('user'),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $sparePartRequest = SparePartRequest::with('user')->find($id);

        if (!$sparePartRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Registro no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $sparePartRequest,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $sparePartRequest = SparePartRequest::find($id);

        if (!$sparePartRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Registro no encontrado.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 5),
            'traction' => 'nullable|string|max:100',
            'origin_country' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.spare_parts_detail' => 'required|string',
            'items.*.spare_part_brand' => 'required|string|max:255',
            'items.*.category' => 'required|string|max:255',
            'items.*.purchase_price' => 'required|numeric|min:0',
            'items.*.public_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada no válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['brand'] = strtoupper(trim($data['brand']));
        $data['model'] = strtoupper(trim($data['model']));
        if (!empty($data['traction'])) {
            $data['traction'] = strtoupper(trim($data['traction']));
        } else {
            $data['traction'] = null;
        }
        if (!empty($data['origin_country'])) {
            $data['origin_country'] = strtoupper(trim($data['origin_country']));
        } else {
            $data['origin_country'] = null;
        }

        // Capitalizar items
        $items = [];
        foreach ($data['items'] as $item) {
            $items[] = [
                'spare_parts_detail' => trim($item['spare_parts_detail']),
                'spare_part_brand' => strtoupper(trim($item['spare_part_brand'])),
                'category' => strtoupper(trim($item['category'])),
                'purchase_price' => floatval($item['purchase_price']),
                'public_price' => floatval($item['public_price']),
            ];
        }
        $data['items'] = $items;

        $sparePartRequest->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Búsqueda de repuesto actualizada con éxito.',
            'data' => $sparePartRequest->load('user'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $sparePartRequest = SparePartRequest::find($id);

        if (!$sparePartRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Registro no encontrado.',
            ], 404);
        }

        $sparePartRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Registro eliminado correctamente.',
        ]);
    }
}
