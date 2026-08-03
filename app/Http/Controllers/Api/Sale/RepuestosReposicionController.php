<?php

namespace App\Http\Controllers\Api\Sale;

use App\Http\Controllers\Controller;
use App\Models\Sales\RepuestosReposicion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepuestosReposicionController extends Controller
{
    /**
     * Obtener listado de repuestos pendientes de reposición.
     */
    public function getPending(): JsonResponse
    {
        try {
            $pending = RepuestosReposicion::with(['supplier', 'product'])
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $pending
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener repuestos a reponer.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar un repuesto como adquirido.
     */
    public function markAsAcquired(int $id): JsonResponse
    {
        try {
            $replacement = RepuestosReposicion::findOrFail($id);
            $replacement->update(['status' => 'acquired']);

            return response()->json([
                'success' => true,
                'message' => 'Repuesto marcado como adquirido exitosamente.',
                'data' => $replacement
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado del repuesto.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
