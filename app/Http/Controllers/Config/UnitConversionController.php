<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Config\UnitConversion;
use Illuminate\Http\Request;

class UnitConversionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $unit_id = $request->get('unit_id');

            if (!$unit_id) {
                return response()->json([
                    'status' => 422,
                    'message' => 'Error de validación',
                    'errors' => ['unit_id' => 'El campo unit_id es requerido'],
                ], 422);
            }

            $unit_conversions = UnitConversion::where('unit_id', $unit_id)
                ->with(['unit', 'unit_to'])
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'status' => 200,
                'unit_conversions' => $unit_conversions->map(function ($unit_conversion) {
                    return [
                        'id' => $unit_conversion->id,
                        'unit_id' => $unit_conversion->unit_id,
                        'unit' => [
                            'id' => $unit_conversion->unit->id,
                            'name' => strtoupper(trim($unit_conversion->unit->name)),
                        ],
                        'unit_to_id' => $unit_conversion->unit_to_id,
                        'unit_to' => [
                            'id' => $unit_conversion->unit_to->id,
                            'name' => strtoupper(trim($unit_conversion->unit_to->name)),
                        ],
                        'created_at' => $unit_conversion->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener las conversiones de unidades',
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
            $request->validate([
                'unit_id' => 'required|integer|exists:units,id',
                'unit_to_id' => 'required|integer|exists:units,id|different:unit_id',
            ]);

            $exist_unit_conversion = UnitConversion::where('unit_id', $request->unit_id)
                ->where('unit_to_id', $request->unit_to_id)
                ->first();

            if ($exist_unit_conversion) {
                return response()->json([
                    'status' => 403,
                    'message' => 'LA UNIDAD A CONVERTIR YA EXISTE',
                ], 403);
            }

            $unit_conversion = UnitConversion::create([
                'unit_id' => $request->unit_id,
                'unit_to_id' => $request->unit_to_id,
            ]);

            // Cargar relaciones para la respuesta
            $unit_conversion->load(['unit', 'unit_to']);

            return response()->json([
                'status' => 200,
                'message' => 'Conversión de unidad creada exitosamente',
                'unit_conversion' => [
                    'id' => $unit_conversion->id,
                    'unit_id' => $unit_conversion->unit_id,
                    'unit' => [
                        'id' => $unit_conversion->unit->id,
                        'name' => strtoupper(trim($unit_conversion->unit->name)),
                    ],
                    'unit_to_id' => $unit_conversion->unit_to_id,
                    'unit_to' => [
                        'id' => $unit_conversion->unit_to->id,
                        'name' => strtoupper(trim($unit_conversion->unit_to->name)),
                    ],
                    'created_at' => $unit_conversion->created_at->format('Y-m-d H:i:s'),
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
                'message' => 'Error al crear la conversión de unidad',
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
            $unit_conversion = UnitConversion::findOrFail($id);
            $unit_conversion->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Conversión de unidad eliminada exitosamente',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Conversión de unidad no encontrada',
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al eliminar la conversión de unidad',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
