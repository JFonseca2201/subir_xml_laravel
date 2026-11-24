<?php

namespace App\Http\Controllers\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Product\Product;
use App\Models\Workshop\WorkshopConsumption;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkshopConsumptionController extends Controller
{
    /**
     * Listado paginado de consumos internos de taller con filtros y métricas.
     */
    public function index(Request $request)
    {
        try {
            $search = trim($request->get('search', ''));
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $productId = $request->get('product_id');
            $userId = $request->get('user_id');
            $perPage = (int) $request->get('per_page', 15);

            $query = WorkshopConsumption::with(['product.unit', 'user'])
                ->orderBy('consumed_at', 'desc');

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('product', function ($pQ) use ($search) {
                        $pQ->where('description', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('brand', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function ($uQ) use ($search) {
                        $uQ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhere('station_or_notes', 'like', "%{$search}%");
                });
            }

            if (!empty($productId)) {
                $query->where('product_id', $productId);
            }

            if (!empty($userId)) {
                $query->where('user_id', $userId);
            }

            if (!empty($startDate)) {
                $query->whereDate('consumed_at', '>=', Carbon::parse($startDate)->toDateString());
            }

            if (!empty($endDate)) {
                $query->whereDate('consumed_at', '<=', Carbon::parse($endDate)->toDateString());
            }

            // Totales acumulados para métricas rápidas
            $totalUnits = (clone $query)->sum('quantity');
            $totalCost = (clone $query)->sum('total_cost');

            $consumptions = $query->paginate($perPage);

            return response()->json([
                'status' => 200,
                'data' => $consumptions->items(),
                'total' => $consumptions->total(),
                'current_page' => $consumptions->currentPage(),
                'last_page' => $consumptions->lastPage(),
                'per_page' => $consumptions->perPage(),
                'summary' => [
                    'total_containers_consumed' => (float) $totalUnits,
                    'total_cost_consumed' => round((float) $totalCost, 2),
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al listar los consumos de taller: ' . $th->getMessage(),
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Listado de insumos de taller por envase/caja cerrada con stock disponible.
     */
    public function supplies(Request $request)
    {
        try {
            $search = trim($request->get('search', ''));

            $query = Product::where('usage_type', Product::USAGE_WORKSHOP_STOCK_UNIT)
                ->with(['unit', 'categorie'])
                ->orderBy('description', 'asc');

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%");
                });
            }

            $products = $query->get()->map(function ($p) {
                $lastConsumption = WorkshopConsumption::where('product_id', $p->id)
                    ->latest('consumed_at')
                    ->first();

                return [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'description' => $p->description,
                    'brand' => $p->brand,
                    'stock' => (float) $p->stock,
                    'unit_name' => $p->unit ? $p->unit->name : 'Envase/Caja',
                    'purchase_price' => (float) $p->purchase_price,
                    'last_consumed_at' => $lastConsumption ? $lastConsumption->consumed_at->toDateTimeString() : null,
                ];
            });

            return response()->json([
                'status' => 200,
                'data' => $products,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al consultar insumos de taller: ' . $th->getMessage(),
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Registro con 1-clic de Salida por Consumo Interno (Abrir Caja/Envase).
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'nullable|numeric|min:0.01',
            'station_or_notes' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();

        try {
            $qtyToConsume = max(0.01, (float) ($request->quantity ?? 1.00));

            // Bloqueo de fila para evitar condiciones de carrera en stock
            $product = Product::lockForUpdate()->findOrFail($request->product_id);

            // Validar que sea un insumo por envase
            if ($product->usage_type !== Product::USAGE_WORKSHOP_STOCK_UNIT) {
                // Permitir si el usuario lo está convirtiendo o confirmar
                $product->usage_type = Product::USAGE_WORKSHOP_STOCK_UNIT;
            }

            if ((float) $product->stock < $qtyToConsume) {
                DB::rollBack();
                return response()->json([
                    'status' => 422,
                    'message' => "Stock insuficiente de envases para '{$product->description}'. Disponibles: {$product->stock}, Solicitados: {$qtyToConsume}",
                ], 422);
            }

            // 1. Reducir stock del envase
            $product->stock = max(0, (float) $product->stock - $qtyToConsume);
            $product->save();

            // 2. Registrar el consumo con el costo actual
            $unitCost = (float) ($product->purchase_price > 0 ? $product->purchase_price : $product->price);
            $totalCost = round($unitCost * $qtyToConsume, 2);

            $consumption = WorkshopConsumption::create([
                'product_id' => $product->id,
                'user_id' => Auth::check() ? Auth::id() : 1,
                'quantity' => $qtyToConsume,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'station_or_notes' => trim((string) $request->station_or_notes) ?: 'Apertura de envase para operaciones de taller',
                'consumed_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 201,
                'message' => "Salida por consumo registrada: se abrió {$qtyToConsume} envase(s) de '{$product->description}'.",
                'data' => [
                    'consumption' => $consumption->load('user', 'product'),
                    'remaining_stock' => (float) $product->stock,
                ],
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al registrar el consumo interno: ' . $th->getMessage(),
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
