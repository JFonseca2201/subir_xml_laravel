<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Supplier\DistributorCatalogItem;
use App\Models\Supplier\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

class DistributorCatalogController extends Controller
{
    /**
     * Listar items de catálogo con filtros y estadísticas.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $supplierId = $request->get('supplier_id');
            $category = $request->get('category');
            $search = $request->get('search');
            $stockStatus = $request->get('stock_status');
            $perPage = (int) $request->get('per_page', 25);
            if ($perPage <= 0) $perPage = 25;

            $query = DistributorCatalogItem::query()->with('supplier:id,name,ruc');

            if ($supplierId && $supplierId !== 'all') {
                $query->where('supplier_id', $supplierId);
            }

            if ($category && $category !== 'ALL' && $category !== 'all') {
                $query->where('category_name', $category);
            }

            if ($stockStatus && $stockStatus !== 'ALL' && $stockStatus !== 'all') {
                $query->where('stock_status', $stockStatus);
            }

            if (!empty($search)) {
                $query->search($search);
            }

            // Estadísticas rápidas según el distribuidor seleccionado
            $statsQuery = DistributorCatalogItem::query();
            if ($supplierId && $supplierId !== 'all') {
                $statsQuery->where('supplier_id', $supplierId);
            }

            $totalProducts = (clone $statsQuery)->count();
            $totalCategories = (clone $statsQuery)->distinct('category_name')->count('category_name');
            $totalAvailable = (clone $statsQuery)->where('stock_status', 'like', '%Disponible%')->count();
            $totalLowStock = (clone $statsQuery)->where('stock_status', 'like', '%Stock <=%')->count();
            $totalTransit = (clone $statsQuery)->where('stock_status', 'like', '%tránsito%')->count();

            $items = $query->orderBy('category_name', 'asc')
                ->orderBy('description', 'asc')
                ->paginate($perPage);

            return response()->json([
                'status' => 200,
                'success' => true,
                'data' => $items->items(),
                'total' => $items->total(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'stats' => [
                    'total_products' => $totalProducts,
                    'total_categories' => $totalCategories,
                    'total_available' => $totalAvailable,
                    'total_low_stock' => $totalLowStock,
                    'total_transit' => $totalTransit,
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Error al consultar catálogo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener listado de categorías disponibles con conteo de items.
     */
    public function categories(Request $request): JsonResponse
    {
        try {
            $supplierId = $request->get('supplier_id');

            $query = DistributorCatalogItem::select('category_name', 'category_code', DB::raw('count(*) as total_items'))
                ->groupBy('category_name', 'category_code');

            if ($supplierId && $supplierId !== 'all') {
                $query->where('supplier_id', $supplierId);
            }

            $categories = $query->orderBy('category_name', 'asc')->get();

            return response()->json([
                'status' => 200,
                'success' => true,
                'data' => $categories
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Error al consultar categorías: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener listado de distribuidores con conteo de items en catálogo.
     */
    public function suppliers(): JsonResponse
    {
        try {
            $suppliers = Supplier::where('is_active', true)
                ->orderBy('name', 'asc')
                ->get()
                ->map(function ($s) {
                    $itemCount = DistributorCatalogItem::where('supplier_id', $s->id)->count();
                    return [
                        'id' => $s->id,
                        'name' => $s->name,
                        'ruc' => $s->ruc,
                        'total_items' => $itemCount,
                    ];
                });

            return response()->json([
                'status' => 200,
                'success' => true,
                'data' => $suppliers
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Error al consultar distribuidores: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Importar catálogo desde JSON/CSV parseado.
     */
    public function import(Request $request): JsonResponse
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $validator = Validator::make($request->all(), [
            'supplier_id' => 'nullable|integer',
            'supplier_name' => 'nullable|string|max:150',
            'mode' => 'nullable|in:replace,append,update',
            'items' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Datos de importación inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $supplierId = $request->input('supplier_id');
            $supplierName = $request->input('supplier_name', 'JAROMA');
            $mode = $request->input('mode', 'replace');
            $items = $request->input('items', []);

            // Resolver o auto-crear Supplier si no existe
            if (!$supplierId && $supplierName) {
                $supplier = Supplier::where('name', 'like', "%{$supplierName}%")->first();
                if (!$supplier) {
                    $supplier = Supplier::create([
                        'name' => strtoupper(trim($supplierName)),
                        'ruc' => '9999999999001',
                        'is_active' => true,
                    ]);
                }
                $supplierId = $supplier->id;
                $supplierName = $supplier->name;
            } elseif ($supplierId) {
                $supplier = Supplier::find($supplierId);
                if ($supplier) {
                    $supplierName = $supplier->name;
                }
            }

            DB::beginTransaction();

            // Si modo es replace, borrar catálogo previo de este distribuidor
            if ($mode === 'replace') {
                DistributorCatalogItem::where('supplier_id', $supplierId)->delete();
            }

            $now = now()->setTimezone('America/Guayaquil');
            $insertBatch = [];
            $batchSize = 400;
            $importedCount = 0;

            foreach ($items as $row) {
                $code = trim((string)($row['code'] ?? ''));
                $description = trim((string)($row['description'] ?? ''));
                if (empty($code) && empty($description)) continue;

                $price = floatval(str_replace(',', '.', (string)($row['price'] ?? 0)));
                $reference = isset($row['reference']) ? trim((string)$row['reference']) : null;
                $categoryCode = isset($row['category_code']) ? trim((string)$row['category_code']) : null;
                $categoryName = isset($row['category_name']) && trim((string)$row['category_name']) !== ''
                    ? strtoupper(trim((string)$row['category_name']))
                    : 'GENERAL';
                $stockStatus = isset($row['stock_status']) && trim((string)$row['stock_status']) !== ''
                    ? trim((string)$row['stock_status'])
                    : 'Disponible';

                $insertBatch[] = [
                    'supplier_id' => $supplierId,
                    'supplier_name' => $supplierName,
                    'code' => $code,
                    'description' => $description,
                    'reference' => $reference,
                    'price' => $price,
                    'category_code' => $categoryCode,
                    'category_name' => $categoryName,
                    'stock_status' => $stockStatus,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($insertBatch) >= $batchSize) {
                    DistributorCatalogItem::insert($insertBatch);
                    $importedCount += count($insertBatch);
                    $insertBatch = [];
                }
            }

            if (!empty($insertBatch)) {
                DistributorCatalogItem::insert($insertBatch);
                $importedCount += count($insertBatch);
            }

            DB::commit();

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => "Se importaron {$importedCount} productos para el distribuidor {$supplierName} exitosamente.",
                'data' => [
                    'supplier_id' => $supplierId,
                    'supplier_name' => $supplierName,
                    'imported_count' => $importedCount,
                ]
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Error al procesar la importación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar catálogo de un distribuidor.
     */
    public function clear(int $supplierId): JsonResponse
    {
        try {
            $deleted = DistributorCatalogItem::where('supplier_id', $supplierId)->delete();
            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => "Se eliminaron {$deleted} productos del catálogo.",
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Error al limpiar catálogo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un registro individual.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $item = DistributorCatalogItem::findOrFail($id);
            $item->delete();

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Producto eliminado del catálogo.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Error al eliminar producto: ' . $e->getMessage()
            ], 500);
        }
    }
}
