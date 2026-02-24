<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Config\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WarehouseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $warehouses = Warehouse::where('name', 'like', '%' . trim($request->search) . '%')
                ->where('sucursale_id', '>', 0)
                ->orderBy('id', 'desc')->get();

            // Cargar relaciones para cada warehouse
            $warehouses->each(function ($warehouse) {
                $warehouse->load('sucursale');
            });

            return response()->json([
                'status' => 200,
                'warehouses' => $warehouses->map(function ($warehouse) {
                    return [
                        'id' => $warehouse->id,
                        'name' => $warehouse->name,
                        'address' => $warehouse->address,
                        'sucursale_id' => $warehouse->sucursale_id,
                        'sucursale' => $warehouse->sucursale ? [
                            'id' => $warehouse->sucursale->id,
                            'name' => $warehouse->sucursale->name,
                        ] : null,
                        'state' => $warehouse->state,
                        'created_at' => $warehouse->created_at,
                    ];
                }),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {

            $maxId = Warehouse::max('id') ?? 0;
            DB::statement('ALTER TABLE warehouses AUTO_INCREMENT = ' . ($maxId + 1));

            $request->validate([
                'name' => 'required|string|unique:warehouses,name|max:255',
                'address' => 'nullable|string|max:1000',
                'sucursale_id' => 'required|integer|exists:sucursales,id',
                'state' => 'required|integer|in:0,1',
            ]);

            $warehouse = Warehouse::where('sucursale_id', $request->sucursale_id)
                ->where('name', trim($request->name))
                ->where('sucursale_id', '>', 0)
                ->first();

            if ($warehouse) {
                return response()->json([
                    'status' => 400,
                    'message' => 'El almacen ya se encuentra registrado.',
                ], 400);
            }

            $data = $request->all();
            $data['name'] = strtoupper(trim($data['name']));
            $data['address'] = $data['address'] ? strtoupper(trim($data['address'])) : null;

            $warehouse = Warehouse::create($data);

            // Cargar la relación para la respuesta
            $warehouse->load('sucursale');

            return response()->json([
                'status' => 201,
                'message' => 'Almacén creado exitosamente',
                'warehouse' => [
                    'id' => $warehouse->id,
                    'name' => $warehouse->name,
                    'address' => $warehouse->address,
                    'sucursale_id' => $warehouse->sucursale_id,
                    'sucursale' => $warehouse->sucursale ? [
                        'id' => $warehouse->sucursale->id,
                        'name' => $warehouse->sucursale->name,
                    ] : null,
                    'state' => $warehouse->state,
                    'created_at' => $warehouse->created_at,
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 422,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            // Cargar la relación si existe
            $warehouse->load('sucursale');

            return response()->json([
                'status' => 200,
                'warehouse' => [
                    'id' => $warehouse->id,
                    'name' => $warehouse->name,
                    'address' => $warehouse->address,
                    'sucursale_id' => $warehouse->sucursale_id,
                    'sucursale' => $warehouse->sucursale ? [
                        'id' => $warehouse->sucursale->id,
                        'name' => $warehouse->sucursale->name,
                    ] : null,
                    'state' => $warehouse->state,
                    'created_at' => $warehouse->created_at,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('warehouses')->ignore($warehouse->id),
                ],
                'address' => 'nullable|string|max:1000',
                'sucursale_id' => 'required|integer|exists:sucursales,id',
                'state' => 'required|integer|in:0,1',
            ]);

            $data = $request->all();
            $data['name'] = strtoupper(trim($data['name']));
            $data['address'] = $data['address'] ? strtoupper(trim($data['address'])) : null;

            //dd($data);

            $warehouse->update($data);

            // Recargar la relación para obtener los datos actualizados
            $warehouse->load('sucursale');

            return response()->json([
                'status' => 200,
                'message' => 'Almacén actualizado exitosamente',
                'warehouse' => [
                    'id' => $warehouse->id,
                    'name' => $warehouse->name,
                    'address' => $warehouse->address,
                    'sucursale_id' => $warehouse->sucursale_id,
                    'sucursale' => $warehouse->sucursale ? [
                        'id' => $warehouse->sucursale->id,
                        'name' => $warehouse->sucursale->name,
                    ] : null,
                    'state' => $warehouse->state,
                    'created_at' => $warehouse->created_at,
                ],
            ], 200);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            if (isset($errors['name'])) {
                return response()->json([
                    'status' => 400,
                    'message' => 'El almacén ya se encuentra registrado.',
                ], 400);
            }
            return response()->json([
                'status' => 422,
                'errors' => $errors,
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            // Verificar si hay productos o relaciones antes de eliminar
            // (Aquí podrías agregar validaciones adicionales si es necesario)

            $warehouse->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Almacén eliminado exitosamente',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
