<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinancialMovement;
use Illuminate\Http\Request;

class KardexController extends Controller
{
    /**
     * Display a listing of kardex movements with filters.
     */
    public function index(Request $request)
    {
        try {
            // Obtener parámetros de filtrado
            $search = $request->get('search', '');
            $categoriaId = $request->get('categoria_id');
            $movimientoTipo = $request->get('movimiento_tipo'); // income, expense, o null para todos
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $perPage = $request->get('per_page', 50);

            // Establecer rango de fechas por defecto (mes actual)
            if (!$startDate || !$endDate) {
                $startDate = now()->startOfMonth()->format('Y-m-d');
                $endDate = now()->endOfMonth()->format('Y-m-d');
            }

            // Construir query con filtros dinámicos
            $query = FinancialMovement::with(['movable', 'account'])
                ->whereBetween('entry_date', [$startDate, $endDate])
                ->orderBy('entry_date', 'desc')
                ->orderBy('created_at', 'desc');

            // Filtro por tipo de movimiento (income/expense)
            if ($movimientoTipo && in_array($movimientoTipo, ['income', 'expense', 'transfer'])) {
                $query->where('type', $movimientoTipo);
            }

            // Filtro por búsqueda en descripción
            if ($search) {
                $query->where('description', 'LIKE', '%' . $search . '%');
            }

            // Paginar resultados
            $movimientos = $query->paginate($perPage);

            // Agrupar movimientos por fecha para la vista
            $movimientosAgrupadosRaw = $movimientos->getCollection()->groupBy(function ($item) {
                return $item->entry_date->format('Y-m-d');
            });

            // Calcular resúmenes por día
            $resumenPorDia = [];
            foreach ($movimientosAgrupadosRaw as $fecha => $movs) {
                $resumenPorDia[$fecha] = [
                    'fecha' => $fecha,
                    'total_ingresos_financieros' => $movs->where('type', 'income')->sum('amount'),
                    'total_egresos_financieros' => $movs->where('type', 'expense')->sum('amount'),
                    'saldo_financiero' => $movs->where('type', 'income')->sum('amount') - $movs->where('type', 'expense')->sum('amount'),
                ];
            }

            // Formatear movimientos para la respuesta
            $movimientosFormateados = [];
            foreach ($movimientos as $movimiento) {
                // Determinar tipo de concepto basado en el movable_type
                $conceptoTipo = $this->getConceptoTipo($movimiento->movable_type);

                $movimientosFormateados[] = [
                    'id' => $movimiento->id,
                    'fecha' => $movimiento->entry_date->format('Y-m-d'),
                    'fecha_formateada' => $movimiento->entry_date->format('d/m/Y'),
                    'movimiento_tipo' => $movimiento->type === 'income' ? 'entrada' : 'salida',
                    'concepto_tipo' => $conceptoTipo,
                    'producto' => null,
                    'servicio' => null,
                    'user' => null,
                    'cantidad_anterior' => null,
                    'cantidad_movida' => null,
                    'cantidad_posterior' => null,
                    'precio_unitario' => null,
                    'subtotal' => null,
                    'total' => null,
                    'monto_financiero' => (float) $movimiento->amount,
                    'referencia_id' => $movimiento->movable_id,
                    'referencia_tipo' => $movimiento->movable_type,
                    'descripcion' => $movimiento->description,
                    'afecta_stock' => false,
                    'account' => $movimiento->account ? [
                        'id' => $movimiento->account->id,
                        'name' => $movimiento->account->name,
                    ] : null,
                    'metadata' => $movimiento->metadata,
                ];
            }

            // Agrupar los movimientos FORMATEADOS por fecha para que el frontend reciba las claves formateadas correctas
            $movimientosAgrupados = collect($movimientosFormateados)->groupBy('fecha');

            return response()->json([
                'status' => 200,
                'message' => 'Movimientos de kardex obtenidos exitosamente',
                'data' => [
                    'movimientos' => $movimientosFormateados,
                    'movimientos_agrupados' => $movimientosAgrupados,
                    'resumen_por_dia' => $resumenPorDia,
                    'pagination' => [
                        'total' => $movimientos->total(),
                        'per_page' => $movimientos->perPage(),
                        'current_page' => $movimientos->currentPage(),
                        'total_pages' => $movimientos->lastPage(),
                    ],
                    'filtros_aplicados' => [
                        'search' => $search,
                        'categoria_id' => $categoriaId,
                        'movimiento_tipo' => $movimientoTipo,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ],
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener los movimientos de kardex',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Determinar el tipo de concepto basado en el movable_type
     */
    private function getConceptoTipo($movableType)
    {
        $mapping = [
            'App\Models\Sale\Sale' => 'venta_producto',
            'App\Models\WorkOrder\WorkOrder' => 'venta_servicio',
            'App\Models\Purchase\Purchase' => 'compra_inventario',
            'App\Models\Employee\EmployeeExpense' => 'pago_sueldo',
            'App\Models\Finance\FinanceRecord' => 'gasto_general',
        ];

        return $mapping[$movableType] ?? 'gasto_general';
    }
}
