<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Config\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $perPage = $request->get('per_page', 10);

            $units = Unit::where('name', 'LIKE', '%' . $search . '%')->orderBy('id', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 200,
                'units' => $units->map(function ($unit) {
                    return [
                        'id' => $unit->id,
                        'name' => strtoupper(trim($unit->name)),
                        'description' => trim($unit->description),
                        'state' => (int) $unit->state,
                        'created_at' => $unit->created_at->format('Y/m/d h:i:s'),
                    ];
                }),
                'total' => $units->total(),
                'per_page' => $units->perPage(),
                'current_page' => $units->currentPage(),
                'total_pages' => $units->lastPage(),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener las unidades',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'state' => 'required|integer|in:1,2',
            ]);

            // Aplicar trim y uppercase al nombre
            $data = $validated;
            $data['name'] = strtoupper(trim($data['name']));
            $data['description'] = isset($data['description']) ? trim($data['description']) : null;

            $exist_unit = Unit::where('name', $data['name'])->first();

            if ($exist_unit) {
                return response()->json([
                    'status' => 403,
                    'message' => 'EL NOMBRE DE LA UNIDAD YA EXISTE, INTENTE UNO NUEVO',
                ], 403);
            }

            $unit = Unit::create($data);

            return response()->json([
                'status' => 200,
                'message' => 'Unidad creada exitosamente',
                'unit' => [
                    'id' => $unit->id,
                    'name' => strtoupper(trim($unit->name)),
                    'description' => trim($unit->description),
                    'state' => (int) $unit->state,
                    'created_at' => $unit->created_at->format('Y/m/d h:i:s'),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al crear la unidad',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $unit = Unit::findOrFail($id);

            return response()->json([
                'status' => 200,
                'unit' => [
                    'id' => $unit->id,
                    'name' => strtoupper(trim($unit->name)),
                    'description' => trim($unit->description),
                    'state' => (int) $unit->state,
                    'created_at' => $unit->created_at->format('Y/m/d h:i:s'),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Unidad no encontrada',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener la unidad',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'state' => 'required|integer|in:1,2',
            ]);

            // Aplicar trim y uppercase al nombre
            $data = $validated;
            $data['name'] = strtoupper(trim($data['name']));
            $data['description'] = isset($data['description']) ? trim($data['description']) : null;

            $exist_unit = Unit::where('name', $data['name'])->where('id', '<>', $id)->first();

            if ($exist_unit) {
                return response()->json([
                    'status' => 403,
                    'message' => 'EL NOMBRE DE LA UNIDAD YA EXISTE, INTENTE UNO NUEVO',
                ], 403);
            }

            $unit = Unit::findOrFail($id);
            $unit->update($data);

            return response()->json([
                'status' => 200,
                'message' => 'Unidad actualizada exitosamente',
                'unit' => [
                    'id' => $unit->id,
                    'name' => strtoupper(trim($unit->name)),
                    'description' => trim($unit->description),
                    'state' => (int) $unit->state,
                    'created_at' => $unit->created_at->format('Y/m/d h:i:s'),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Unidad no encontrada',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar la unidad',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $unit = Unit::findOrFail($id);
            $unit->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Unidad eliminada exitosamente',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Unidad no encontrada',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al eliminar la unidad',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
