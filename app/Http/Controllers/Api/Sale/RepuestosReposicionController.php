<?php

namespace App\Http\Controllers\Api\Sale;

use App\Http\Controllers\Controller;
use App\Models\Sales\RepuestosReposicion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepuestosReposicionController extends Controller
{
    /**
     * Obtener listado de repuestos pendientes de reposición con paginación optimizada para scroll infinito.
     */
    public function getPending(Request $request): JsonResponse
    {
        try {
            $search = trim((string) $request->input('search', ''));
            $perPage = max(1, min((int) $request->input('per_page', 20), 100));
            $page = max(1, (int) $request->input('page', 1));

            // Total global de pendientes en el sistema para contadores y badges
            $totalPending = RepuestosReposicion::where('status', 'pending')->count();

            $query = RepuestosReposicion::with([
                'supplier:id,name,ruc',
                'product:id,description,sku'
            ])->where('status', 'pending');

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhereHas('supplier', function ($sq) use ($search) {
                          $sq->where('name', 'like', "%{$search}%")
                             ->orWhere('ruc', 'like', "%{$search}%");
                      });
                });
            }

            $paginated = $query->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $paginated->items(),
                'total' => $paginated->total(),
                'total_pending' => $totalPending,
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'has_more' => $paginated->hasMorePages(),
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
