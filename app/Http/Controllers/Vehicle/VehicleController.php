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
                    ->orWhere('description', 'like', "%$search%");
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
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $exists = Vehicle::where('license_plate', $request->license_plate)->first();
        if ($exists) {
            return response()->json([
                'status' => 422,
                'message' => 'El vehículo con la placa ' . $request->license_plate . ' ya se encuentra registrado',
                'errors' => ['license_plate' => 'El vehículo con la placa ' . $request->license_plate . ' ya se encuentra registrado'],
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
                // Regex de Ecuador: soporta los 4 formatos que pusimos en Vue
                'regex:/^([A-Z]{3}-\d{3,4}|[A-Z]-\d{3}[A-Z]|\d{4}-[A-Z]{3})$/'
            ],
            'brand'        => 'required',
            'model'        => 'required|string|max:100',
            'year'         => 'required|integer|min:1900|max:' . (date('Y') + 5),
            'color'        => 'required',
            'vehicle_type' => 'required',
            'description'  => 'nullable|string|max:1000',
            'status'       => 'required|integer|in:1,2',
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
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $vehicle = Vehicle::find($id);

        if (!$vehicle) {
            return response()->json(['status' => 404, 'message' => 'Vehículo no encontrado'], 404);
        }

        $requestData = $request->all();

        // Extraer valores si el frontend los envía como objetos (comportamiento de Vuetify v-select)
        foreach (['vehicle_type', 'brand', 'color'] as $field) {
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
                'regex:/^([A-Z]{3}-\d{3,4}|[A-Z]-\d{3}[A-Z]|\d{4}-[A-Z]{3})$/'
            ],
            'brand'        => 'required',
            'model'        => 'required|string|max:100',
            'year'         => 'required|integer|min:1900|max:' . (date('Y') + 5),
            'color'        => 'required',
            'vehicle_type' => 'required',
            'description'  => 'nullable|string|max:1000',
            'status'       => 'required|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

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