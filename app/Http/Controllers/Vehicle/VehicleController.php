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
     * Get vehicle types from config.
     */
    public function getVehicleTypes()
    {
        $vehicleTypes = config('vehicle_types');
        return response()->json([
            'status' => 200,
            'types' => $vehicleTypes,
            'total' => count($vehicleTypes),
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
        $vehicles = $query->orderBy('id', 'desc')->paginate($per_page);

        return response()->json($vehicles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Obtener opciones permitidas desde config para validar
        $allowedTypes = array_keys(config('vehicle_types', []));
        // Si en tu config las marcas son ID => Nombre, validamos contra los IDs
        $allowedBrands = array_keys(config('vehicle_brands', []));

        $maxId = Vehicle::max('id') ?? 0;
        DB::statement('ALTER TABLE vehicles AUTO_INCREMENT = ' . ($maxId + 1));

        // 2. Validación Robusta
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
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
            'color'        => 'required|string',
            'vehicle_type' => 'required|string',
            'description'  => 'nullable|string|max:1000',
            'status'       => 'default_1',
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

        // 3. Creación limpia (El frontend ya envía los datos formateados)
        $vehicle = Vehicle::create($request->only([
            'license_plate',
            'brand',
            'model',
            'year',
            'color',
            'vehicle_type',
            'description',
            'user_id',
            'status'
        ]));

        return response()->json([
            'status' => 201,
            'message' => 'Vehículo registrado exitosamente',
            'vehicle' => $vehicle,
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

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'license_plate' => [
                'required',
                'string',
                Rule::unique('vehicles')->ignore($id),
                'regex:/^([A-Z]{3}-\d{3,4}|[A-Z]-\d{3}[A-Z]|\d{4}-[A-Z]{3})$/'
            ],
            'brand'        => 'required',
            'model'        => 'required|string|max:100',
            'year'         => 'required|integer|min:1900|max:' . (date('Y') + 5),
            'color'        => 'required|string',
            'vehicle_type' => 'required|string',
            'description'  => 'nullable|string|max:1000',
            'status'       => 'required|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        $vehicle->update($request->all());

        return response()->json([
            'status' => 200,
            'message' => 'Vehículo actualizado',
            'vehicle' => $vehicle->fresh(),
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
