<?php

namespace App\Http\Controllers\Vehicle;

use App\Http\Controllers\Controller;
use App\Models\Vehicles\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class VehicleController extends Controller
{
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

        // Filtros
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('license_plate', 'like', '%' . $search . '%')
                    ->orWhere('brand', 'like', '%' . $search . '%')
                    ->orWhere('model', 'like', '%' . $search . '%')
                    ->orWhere('color', 'like', '%' . $search . '%')
                    ->orWhere('vehicle_type', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if ($request->has('brand')) {
            $query->where('brand', $request->get('brand'));
        }

        if ($request->has('year')) {
            $query->where('year', $request->get('year'));
        }

        if ($request->has('color')) {
            $query->where('color', $request->get('color'));
        }

        if ($request->has('vehicle_type')) {
            $query->where('vehicle_type', $request->get('vehicle_type'));
        }

        // Paginación
        $page = $request->get('page', 1);
        $per_page = $request->get('per_page', 10);

        $vehicles = $query->orderBy('id', 'desc')
            ->paginate($per_page, ['*'], 'page', $page);

        return response()->json([
            'total' => $vehicles->total(),
            'count' => $vehicles->count(),
            'per_page' => $vehicles->perPage(),
            'current_page' => $vehicles->currentPage(),
            'total_pages' => $vehicles->lastPage(),
            'from' => $vehicles->firstItem(),
            'to' => $vehicles->lastItem(),
            'has_more_pages' => $vehicles->hasMorePages(),
            'next_page_url' => $vehicles->nextPageUrl(),
            'prev_page_url' => $vehicles->previousPageUrl(),
            'first_page_url' => $vehicles->url(1),
            'last_page_url' => $vehicles->url($vehicles->lastPage()),
            'vehicles' => $vehicles->items(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $vehicleTypes = config('vehicle_types');
        $allowedTypes = implode(',', array_values($vehicleTypes));

        // Normalizar el tipo de vehículo (primera letra mayúscula)
        $vehicleType = $request->get('vehicle_type');
        $normalizedType = ucfirst(strtolower($vehicleType));

        // Debug: Log para verificar valores
        Log::info('Vehicle type received: ' . $vehicleType);
        Log::info('Normalized type: ' . $normalizedType);
        Log::info('Allowed types: ' . $allowedTypes);

        // Reemplazar el valor normalizado en el request
        $request->merge(['vehicle_type' => $normalizedType]);

        $validator = Validator::make($request->all(), [
            'license_plate' => 'required|string|max:20|unique:vehicles',
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'required|string|max:50',
            'vehicle_type' => 'required|string|in:' . $allowedTypes,
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            // Debug: Mostrar información detallada del error
            $errors = $validator->errors();
            Log::error('Validation errors: ' . json_encode($errors->all()));

            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $errors,
                'debug_info' => [
                    'received_type' => $request->get('vehicle_type'),
                    'allowed_types' => $allowedTypes,
                    'vehicle_types_config' => $vehicleTypes
                ]
            ], 422);
        }

        $vehicle = Vehicle::create([
            'license_plate' => $request->get('license_plate'),
            'brand' => $request->get('brand'),
            'model' => $request->get('model'),
            'year' => $request->get('year'),
            'color' => $request->get('color'),
            'vehicle_type' => $request->get('vehicle_type'),
            'description' => $request->get('description'),
        ]);

        return response()->json([
            'status' => 201,
            'message' => 'Vehículo creado exitosamente',
            'vehicle' => $vehicle,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $vehicle = Vehicle::find($id);

        if (!$vehicle) {
            return response()->json([
                'status' => 404,
                'message' => 'Vehículo no encontrado',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'vehicle' => $vehicle,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $vehicle = Vehicle::find($id);

        if (!$vehicle) {
            return response()->json([
                'status' => 404,
                'message' => 'Vehículo no encontrado',
            ], 404);
        }

        $vehicleTypes = config('vehicle_types');
        $allowedTypes = implode(',', array_values($vehicleTypes));

        // Normalizar el tipo de vehículo (primera letra mayúscula)
        $vehicleType = $request->get('vehicle_type');
        $normalizedType = ucfirst(strtolower($vehicleType));

        // Reemplazar el valor normalizado en el request
        $request->merge(['vehicle_type' => $normalizedType]);

        $validator = Validator::make($request->all(), [
            'license_plate' => 'required|string|max:20|unique:vehicles,license_plate,' . $id,
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'required|string|max:50',
            'vehicle_type' => 'required|string|in:' . $allowedTypes,
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $vehicle->update([
            'license_plate' => $request->get('license_plate'),
            'brand' => $request->get('brand'),
            'model' => $request->get('model'),
            'year' => $request->get('year'),
            'color' => $request->get('color'),
            'vehicle_type' => $request->get('vehicle_type'),
            'description' => $request->get('description'),
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Vehículo actualizado exitosamente',
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
            return response()->json([
                'status' => 404,
                'message' => 'Vehículo no encontrado',
            ], 404);
        }

        $vehicle->delete(); // Soft delete

        return response()->json([
            'status' => 200,
            'message' => 'Vehículo eliminado exitosamente',
        ]);
    }

    /**
     * Restore a soft deleted vehicle.
     */
    public function restore(string $id)
    {
        $vehicle = Vehicle::withTrashed()->find($id);

        if (!$vehicle) {
            return response()->json([
                'status' => 404,
                'message' => 'Vehículo no encontrado',
            ], 404);
        }

        if (!$vehicle->trashed()) {
            return response()->json([
                'status' => 422,
                'message' => 'El vehículo no está eliminado',
            ], 422);
        }

        $vehicle->restore();

        return response()->json([
            'status' => 200,
            'message' => 'Vehículo restaurado exitosamente',
            'vehicle' => $vehicle->fresh(),
        ]);
    }

    /**
     * Force delete a vehicle permanently.
     */
    public function forceDelete(string $id)
    {
        $vehicle = Vehicle::withTrashed()->find($id);

        if (!$vehicle) {
            return response()->json([
                'status' => 404,
                'message' => 'Vehículo no encontrado',
            ], 404);
        }

        if (!$vehicle->trashed()) {
            return response()->json([
                'status' => 422,
                'message' => 'El vehículo no está eliminado',
            ], 422);
        }

        $vehicle->forceDelete();

        return response()->json([
            'status' => 200,
            'message' => 'Vehículo eliminado permanentemente',
        ]);
    }
}
