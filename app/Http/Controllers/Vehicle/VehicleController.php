<?php

namespace App\Http\Controllers\Vehicle;

use App\Http\Controllers\Controller;
use App\Models\Vehicles\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    /**
     * Get vehicle brands from config.
     */
    public function getVehicleBrands()
    {
        $vehicleBrands = config('vehicle_brands');
        return response()->json([
            'status' => 200,
            'brands' => $vehicleBrands,
            'total' => count($vehicleBrands),
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Vehicle::query();

        // Búsqueda global
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('license_plate', 'like', "%$search%")
                    ->orWhere('model', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%")
                    ->orWhereHas('client', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%$search%")
                           ->orWhere('surname', 'like', "%$search%")
                           ->orWhere('full_name', 'like', "%$search%")
                           ->orWhere('n_document', 'like', "%$search%");
                    });
            });
        }

        // Filtros exactos
        foreach (['brand', 'year', 'color', 'vehicle_type'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->get($filter));
            }
        }

        $per_page = $request->get('per_page', 10);
        $vehicles = $query->with('client')->orderBy('id', 'desc')->paginate($per_page);

        return response()->json($vehicles);
    }

    /**
     * Search vehicles for autocomplete or lazy loading.
     */
    public function search(Request $request)
    {
        $search = trim($request->get('q', $request->get('search', '')));
        $clientId = $request->get('client_id');

        if (strlen($search) < 2 && !$clientId) {
            return response()->json([
                'status' => 200,
                'data' => [],
            ]);
        }

        $query = Vehicle::select([
            'id',
            'client_id',
            'license_plate',
            'model',
            'brand',
            'year',
            'color',
            'description',
            'vehicle_type',
            'usage_type',
            'status',
        ])->with(['client:id,name,surname,full_name,n_document,email,phone,address']);

        // Si no se escribe texto de búsqueda pero sí hay client_id, mostrar los vehículos de ese cliente
        if ($clientId && $search === '') {
            $query->where('client_id', $clientId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('license_plate', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('surname', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%")
                            ->orWhere('n_document', 'like', "%{$search}%");
                    });
            });
        }

        if ($clientId) {
            $query->orderByRaw("CASE WHEN client_id = ? THEN 0 ELSE 1 END", [$clientId]);
        }

        $vehicles = $query->orderBy('id', 'desc')->limit(15)->get();

        return response()->json([
            'status' => 200,
            'data' => $vehicles,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $cleanPlate = strtoupper(trim((string)$request->license_plate));
        $exists = Vehicle::whereRaw('LOWER(TRIM(license_plate)) = ?', [strtolower($cleanPlate)])->first();
        if ($exists) {
            return response()->json([
                'status' => 422,
                'message' => 'El vehículo con la placa ' . $cleanPlate . ' ya se encuentra registrado',
                'errors' => ['license_plate' => 'El vehículo con la placa ' . $cleanPlate . ' ya se encuentra registrado'],
            ], 422);
        }

        // 1. Obtener opciones permitidas desde config para validar
        $allowedTypes = array_keys(config('vehicle_types', []));
        // Si en tu config las marcas son ID => Nombre, validamos contra los IDs
        $allowedBrands = array_keys(config('vehicle_brands', []));

        $requestData = $request->all();

        // Extraer valores si el frontend los envía como objetos (comportamiento de Vuetify v-select)
        foreach (['vehicle_type', 'brand', 'color'] as $field) {
            if (isset($requestData[$field]) && is_array($requestData[$field])) {
                $requestData[$field] = $requestData[$field]['value'] ?? $requestData[$field]['title'] ?? $requestData[$field];
            }
        }

        // 2. Validación Robusta
        $validator = Validator::make($requestData, [
            'user_id' => 'required|exists:users,id',
            'client_id' => 'required|exists:clients,id',
            'license_plate' => [
                'required',
                'string',
                'unique:vehicles,license_plate',
                // Regex de Ecuador: soporta los 4 formatos de Ecuador + formatos genéricos de sin placa
                'regex:/^([A-Z]{3}-\d{3,4}|[A-Z]-\d{3}[A-Z]|\d{4}-[A-Z]{3}|SIN-PLACA|SIN PLACA|S\/P|S\/P-[A-Z0-9]+|SP-\d{3,4})$/i'
            ],
            'brand' => 'required',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 5),
            'color' => 'required',
            'vehicle_type' => 'required',
            'usage_type' => 'nullable|string|in:particular,taxi,comercial,pesado',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|integer|in:1,2',
        ], [
            'license_plate.regex' => 'El formato de la placa es inválido para Ecuador.',
            'license_plate.unique' => 'Esta placa ya está registrada en el sistema.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $requestData = $validator->validated();

        if (empty($requestData['usage_type'])) {
            $requestData['usage_type'] = 'particular';
        }

        // 3. Asegurar que el status sea válido (1 = activo, 2 = inactivo)
        if (!isset($requestData['status']) || !in_array($requestData['status'], [1, 2])) {
            $requestData['status'] = 1; // Por defecto activo
        }

        // 4. Creación limpia (El frontend ya envía los datos formateados)
        $vehicle = Vehicle::create($requestData);

        return response()->json([
            'status' => 201,
            'message' => 'Vehículo registrado exitosamente',
            'vehicle' => $vehicle->load('client'),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $vehicle = Vehicle::with(['client', 'creator'])->find($id);

        if (!$vehicle) {
            return response()->json(['status' => 404, 'message' => 'No encontrado'], 404);
        }

        return response()->json([
            'status' => 200,
            'vehicle' => $vehicle,
        ]);
    }

    /**
     * Obtener o crear vehículo por defecto (Sin Placa / Servicio General)
     */
    public function getDefaultVehicle(Request $request)
    {
        $clientId = $request->get('client_id');

        // 1. Si se provee client_id, buscar si ese cliente tiene un vehículo sin placa
        if ($clientId) {
            $existing = Vehicle::where('client_id', $clientId)
                ->where(function ($q) {
                    $q->where('license_plate', 'like', '%SIN%PLACA%')
                        ->orWhere('license_plate', 'like', '%S/P%')
                        ->orWhere('model', 'like', '%SERVICIO GENERAL%');
                })
                ->with(['client:id,name,surname,full_name,n_document,email,phone,address'])
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 200,
                    'vehicle' => $existing,
                    'data' => $existing,
                ]);
            }
        }

        // 2. Buscar cualquier vehículo comodín general
        $defaultVehicle = Vehicle::where(function ($q) {
            $q->where('license_plate', 'SIN-PLACA')
                ->orWhere('license_plate', 'S/P')
                ->orWhere('model', 'like', '%SERVICIO GENERAL%');
        })
        ->with(['client:id,name,surname,full_name,n_document,email,phone,address'])
        ->first();

        if (!$defaultVehicle) {
            $userId = auth()->id() ?? auth('api')->id() ?? \App\Models\User::first()?->id ?? 1;
            $targetClientId = $clientId ?: (\App\Models\Client\Client::first()?->id ?? 1);

            $defaultVehicle = Vehicle::create([
                'user_id' => $userId,
                'client_id' => $targetClientId,
                'license_plate' => 'SIN-PLACA',
                'brand' => 'GENÉRICO',
                'model' => 'SERVICIO GENERAL / SIN PLACA',
                'year' => (int)date('Y'),
                'color' => 'BLANCO',
                'vehicle_type' => 'liviano',
                'usage_type' => 'particular',
                'description' => 'Vehículo por defecto para servicios generales o sin placa',
                'status' => 1,
            ]);

            $defaultVehicle->load(['client:id,name,surname,full_name,n_document,email,phone,address']);
        }

        return response()->json([
            'status' => 200,
            'vehicle' => $defaultVehicle,
            'data' => $defaultVehicle,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $cleanPlate = strtoupper(trim((string)$request->license_plate));
        $vehicle = Vehicle::find($id);

        if (!$vehicle) {
            return response()->json(['status' => 404, 'message' => 'No encontrado'], 404);
        }

        $exists = Vehicle::whereRaw('LOWER(TRIM(license_plate)) = ?', [strtolower($cleanPlate)])
            ->where('id', '!=', $id)
            ->first();

        if ($exists) {
            return response()->json([
                'status' => 422,
                'message' => 'El vehículo con la placa ' . $cleanPlate . ' ya se encuentra registrado',
                'errors' => ['license_plate' => 'El vehículo con la placa ' . $cleanPlate . ' ya se encuentra registrado'],
            ], 422);
        }

        $requestData = $request->all();

        // Extraer valores si el frontend los envía como objetos (comportamiento de Vuetify v-select)
        foreach (['vehicle_type', 'brand', 'color', 'usage_type'] as $field) {
            if (isset($requestData[$field]) && is_array($requestData[$field])) {
                $requestData[$field] = $requestData[$field]['value'] ?? $requestData[$field]['title'] ?? $requestData[$field];
            }
        }

        $validator = Validator::make($requestData, [
            'user_id' => 'required|exists:users,id',
            'client_id' => 'required|exists:clients,id',
            'license_plate' => [
                'required',
                'string',
                Rule::unique('vehicles')->ignore($id),
                'regex:/^([A-Z]{3}-\d{3,4}|[A-Z]-\d{3}[A-Z]|\d{4}-[A-Z]{3}|SIN-PLACA|SIN PLACA|S\/P|S\/P-[A-Z0-9]+|SP-\d{3,4})$/i'
            ],
            'brand' => 'required',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 5),
            'color' => 'required',
            'vehicle_type' => 'required',
            'usage_type' => 'nullable|string|in:particular,taxi,comercial,pesado',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        $requestData = $validator->validated();
        $vehicle->update($requestData);

        return response()->json([
            'status' => 200,
            'message' => 'Vehículo actualizado',
            'vehicle' => $vehicle->fresh('client'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $vehicle = Vehicle::find($id);

        if (!$vehicle) {
            return response()->json(['status' => 404, 'message' => 'No encontrado'], 404);
        }

        $vehicle->delete();

        return response()->json(['status' => 200, 'message' => 'Eliminado correctamente']);
    }
}
