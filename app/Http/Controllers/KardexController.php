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

            // Filtro por búsqueda en descripción y/o artículos vendidos/comprados
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'LIKE', '%' . $search . '%')
                        ->orWhere(function ($subQuery) use ($search) {
                            $subQuery->whereHasMorph('movable', ['App\Models\Sales\Sale', 'App\Models\Sale\Sale'], function ($saleQuery) use ($search) {
                                $saleQuery->whereHas('details', function ($detailQuery) use ($search) {
                                    $detailQuery->where('description', 'LIKE', '%' . $search . '%')
                                        ->orWhereHas('product', function ($prodQuery) use ($search) {
                                            $prodQuery->where('sku', 'LIKE', '%' . $search . '%')
                                                ->orWhere('code_aux', 'LIKE', '%' . $search . '%')
                                                ->orWhere('description', 'LIKE', '%' . $search . '%');
                                        });
                                });
                            });
                        })
                        ->orWhere(function ($subQuery) use ($search) {
                            $subQuery->whereHasMorph('movable', ['App\Models\Finance\PaymentDistribution', 'App\Models\PaymentDistribution'], function ($distQuery) use ($search) {
                                $distQuery->whereHas('financeRecord', function ($recordQuery) use ($search) {
                                    $recordQuery->whereIn('invoice_number', function ($invoiceQuery) use ($search) {
                                        $invoiceQuery->select('invoice_number')
                                            ->from('invoices')
                                            ->whereIn('id', function ($itemSubQuery) use ($search) {
                                                $itemSubQuery->select('invoice_id')
                                                    ->from('invoice_items')
                                                    ->where('description', 'LIKE', '%' . $search . '%')
                                                    ->orWhere('code', 'LIKE', '%' . $search . '%');
                                            });
                                    });
                                });
                            });
                        });
                });
            }

            // Paginar resultados
            $movimientos = $query->paginate($perPage);

            // Cargar de manera diferida la relación de detalles y productos para las ventas y compras
            $movimientos->getCollection()->loadMorph('movable', [
                'App\Models\Sales\Sale' => ['details.product'],
                'App\Models\Sale\Sale' => ['details.product'],
                'App\Models\Finance\PaymentDistribution' => ['financeRecord']
            ]);

            // Pre-calcular el mapa de movimientos más antiguos para evitar duplicar productos en facturas con pagos múltiples
            $invoiceNumbers = [];
            foreach ($movimientos as $m) {
                if ($m->movable_type === 'App\Models\Finance\PaymentDistribution') {
                    $dist = $m->movable;
                    if ($dist && $dist->financeRecord) {
                        $invoiceNumbers[] = $dist->financeRecord->invoice_number;
                    }
                }
            }
            $invoiceNumbers = array_unique(array_filter($invoiceNumbers));

            $earliestMovementsMap = [];
            if (!empty($invoiceNumbers)) {
                $allMovementsForInvoices = FinancialMovement::whereHasMorph(
                    'movable',
                    ['App\Models\Finance\PaymentDistribution', 'App\Models\PaymentDistribution'],
                    function ($q) use ($invoiceNumbers) {
                        $q->whereHas('financeRecord', function ($subQ) use ($invoiceNumbers) {
                            $subQ->whereIn('invoice_number', $invoiceNumbers);
                        });
                    }
                )->get();

                $groupedMovements = [];
                foreach ($allMovementsForInvoices as $m) {
                    $dist = $m->movable;
                    if ($dist && $dist->financeRecord) {
                        $invNum = $dist->financeRecord->invoice_number;
                        $groupedMovements[$invNum][] = $m;
                    }
                }

                foreach ($groupedMovements as $invNum => $movs) {
                    usort($movs, function ($a, $b) {
                        $dateA = $a->entry_date->format('Y-m-d');
                        $dateB = $b->entry_date->format('Y-m-d');
                        if ($dateA === $dateB) {
                            return $a->id <=> $b->id;
                        }
                        return strcmp($dateA, $dateB);
                    });
                    $earliestMovementsMap[$invNum] = $movs[0]->id;
                }
            }

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

                // Caso 1: Ventas por artículo
                if ($movimiento->movable_type === 'App\Models\Sales\Sale' || $movimiento->movable_type === 'App\Models\Sale\Sale') {
                    $sale = $movimiento->movable;
                    if ($sale && $sale->relationLoaded('details') && $sale->details->isNotEmpty()) {
                        foreach ($sale->details as $detail) {
                            if ($search) {
                                $matchesSearch = stripos($detail->description, $search) !== false ||
                                    ($detail->product && (
                                        stripos($detail->product->sku, $search) !== false ||
                                        stripos($detail->product->code_aux, $search) !== false ||
                                        stripos($detail->product->description, $search) !== false
                                    ));
                                $matchesGeneral = stripos($movimiento->description, $search) !== false;
                                if (!$matchesSearch && !$matchesGeneral) {
                                    continue;
                                }
                            }

                            $codigo = null;
                            if ($detail->product) {
                                $codigo = $detail->product->sku ?: $detail->product->code_aux;
                            }
                            $concepto = $codigo ?: 'VENTA';

                            $movimientosFormateados[] = [
                                'id' => $movimiento->id . '_sale_detail_' . $detail->id,
                                'fecha' => $movimiento->entry_date->format('Y-m-d'),
                                'fecha_formateada' => $movimiento->entry_date->format('d/m/Y'),
                                'movimiento_tipo' => 'entrada',
                                'concepto_tipo' => 'venta_producto',
                                'concepto' => $concepto,
                                'codigo_aux' => $detail->product ? $detail->product->code_aux : null,
                                'producto' => [
                                    'id' => $detail->product_id,
                                    'description' => $detail->description,
                                    'sku' => $detail->product ? $detail->product->sku : null,
                                ],
                                'servicio' => null,
                                'user' => null,
                                'cantidad_anterior' => null,
                                'cantidad_movida' => (float) $detail->quantity,
                                'cantidad_posterior' => null,
                                'precio_unitario' => (float) $detail->price,
                                'subtotal' => (float) ($detail->price * $detail->quantity),
                                'total' => (float) $detail->total,
                                'monto_financiero' => (float) $detail->total,
                                'referencia_id' => $movimiento->movable_id,
                                'referencia_tipo' => $movimiento->movable_type,
                                'descripcion' => $detail->description /* . ' (Cantidad: ' . $detail->quantity . ') - Venta #' . $sale->document_number */ ,
                                'afecta_stock' => true,
                                'account' => $movimiento->account ? [
                                    'id' => $movimiento->account->id,
                                    'name' => $movimiento->account->name,
                                ] : null,
                                'metadata' => $movimiento->metadata,
                            ];
                        }
                        continue;
                    }
                }

                // Caso 2: Compras por artículo
                if ($movimiento->movable_type === 'App\Models\Finance\PaymentDistribution') {
                    $distribution = $movimiento->movable;
                    if ($distribution && $distribution->financeRecord) {
                        $invoiceNumber = $distribution->financeRecord->invoice_number;

                        // Verificar si este movimiento es el más antiguo/primero para esta factura
                        $isEarliest = isset($earliestMovementsMap[$invoiceNumber]) && $earliestMovementsMap[$invoiceNumber] == $movimiento->id;

                        if ($isEarliest) {
                            // Buscar la Factura de compra correspondiente
                            $invoice = \App\Models\Invoice\Invoice::with('invoice_items')
                                ->where('invoice_number', $invoiceNumber)
                                ->first();

                            if ($invoice && $invoice->invoice_items->isNotEmpty()) {
                                // Calcular factor de escala para asociar proporcionalmente el pago a los artículos
                                $paymentAmount = (float) $movimiento->amount;
                                $invoiceTotal = (float) $invoice->total;
                                $scaleFactor = $invoiceTotal > 0 ? ($paymentAmount / $invoiceTotal) : 1.0;

                                foreach ($invoice->invoice_items as $item) {
                                    if ($search) {
                                        $matchesSearch = stripos($item->description, $search) !== false ||
                                            stripos($item->code, $search) !== false;
                                        $matchesGeneral = stripos($movimiento->description, $search) !== false;
                                        if (!$matchesSearch && !$matchesGeneral) {
                                            continue;
                                        }
                                    }

                                    $codigo = $item->code;
                                    $concepto = $codigo ?: 'COMPRA';

                                    $product = \App\Models\Product\Product::where('sku', $item->code)->first();
                                    $codigoAux = $product ? $product->code_aux : null;

                                    $scaledAmount = (float) ($item->total * $scaleFactor);

                                    $movimientosFormateados[] = [
                                        'id' => $movimiento->id . '_invoice_item_' . $item->id,
                                        'fecha' => $movimiento->entry_date->format('Y-m-d'),
                                        'fecha_formateada' => $movimiento->entry_date->format('d/m/Y'),
                                        'movimiento_tipo' => 'salida',
                                        'concepto_tipo' => 'compra_inventario',
                                        'concepto' => $concepto,
                                        'codigo_aux' => $codigoAux,
                                        'producto' => [
                                            'id' => $product ? $product->id : null,
                                            'description' => $item->description,
                                            'sku' => $item->code,
                                        ],
                                        'servicio' => null,
                                        'user' => null,
                                        'cantidad_anterior' => null,
                                        'cantidad_movida' => (float) $item->quantity,
                                        'cantidad_posterior' => null,
                                        'precio_unitario' => (float) $item->unit_price,
                                        'subtotal' => (float) $item->subtotal,
                                        'total' => (float) $item->total,
                                        'monto_financiero' => $scaledAmount,
                                        'referencia_id' => $movimiento->movable_id,
                                        'referencia_tipo' => $movimiento->movable_type,
                                        'descripcion' => $item->description,
                                        'afecta_stock' => true,
                                        'account' => $movimiento->account ? [
                                            'id' => $movimiento->account->id,
                                            'name' => $movimiento->account->name,
                                        ] : null,
                                        'metadata' => $movimiento->metadata,
                                    ];
                                }
                                continue;
                            }
                        } else {
                            // Si hay búsqueda activa, validar si coincide con la descripción del pago o el número de factura
                            if ($search) {
                                $matchesSearch = stripos($movimiento->description, $search) !== false ||
                                    stripos($invoiceNumber, $search) !== false;
                                if (!$matchesSearch) {
                                    continue;
                                }
                            }

                            // Egresos subsecuentes únicamente como abono financiero
                            $movimientosFormateados[] = [
                                'id' => $movimiento->id . '_financial_payment',
                                'fecha' => $movimiento->entry_date->format('Y-m-d'),
                                'fecha_formateada' => $movimiento->entry_date->format('d/m/Y'),
                                'movimiento_tipo' => 'salida',
                                'concepto_tipo' => 'gasto_general',
                                'concepto' => 'PAGO COMPRA',
                                'codigo_aux' => null,
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
                                'descripcion' => $movimiento->description ?: ('Pago de factura de compra #' . $invoiceNumber),
                                'afecta_stock' => false,
                                'account' => $movimiento->account ? [
                                    'id' => $movimiento->account->id,
                                    'name' => $movimiento->account->name,
                                ] : null,
                                'metadata' => $movimiento->metadata,
                            ];
                            continue;
                        }
                    }
                }

                // Caso 3: Otros movimientos financieros (nómina, adelantos, transferencias, logística, etc.)
                $concepto = $this->getConceptoDisplay($movimiento->movable_type, $movimiento->description);

                if ($movimiento->type === 'transfer' || $movimiento->movable_type === 'App\Models\Finance\InternalTransfer') {
                    $toAccountId = $movimiento->metadata['to_account'] ?? null;
                    $fromAccountId = $movimiento->metadata['from_account'] ?? null;

                    // fetch both names if needed
                    $toAccountName = null;
                    if ($toAccountId) {
                        $toAccount = \App\Models\Finance\Account::find($toAccountId);
                        $toAccountName = $toAccount ? $toAccount->name : 'Cuenta Destino';
                    }

                    // 1. Salida (Gasto) - From Account
                    $movimientosFormateados[] = [
                        'id' => $movimiento->id . '_out',
                        'fecha' => $movimiento->entry_date->format('Y-m-d'),
                        'fecha_formateada' => $movimiento->entry_date->format('d/m/Y'),
                        'movimiento_tipo' => 'salida',
                        'concepto_tipo' => 'transferencia',
                        'concepto' => 'TRANSFERENCIA (SALIDA)',
                        'codigo_aux' => null,
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
                        'descripcion' => $movimiento->description . ' (Hacia: ' . $toAccountName . ')',
                        'afecta_stock' => false,
                        'account' => $movimiento->account ? [
                            'id' => $movimiento->account->id,
                            'name' => $movimiento->account->name,
                        ] : null,
                        'metadata' => $movimiento->metadata,
                    ];

                    // 2. Entrada (Ingreso) - To Account
                    $movimientosFormateados[] = [
                        'id' => $movimiento->id . '_in',
                        'fecha' => $movimiento->entry_date->format('Y-m-d'),
                        'fecha_formateada' => $movimiento->entry_date->format('d/m/Y'),
                        'movimiento_tipo' => 'entrada',
                        'concepto_tipo' => 'transferencia',
                        'concepto' => 'TRANSFERENCIA (INGRESO)',
                        'codigo_aux' => null,
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
                        'descripcion' => $movimiento->description . ' (Desde: ' . ($movimiento->account ? $movimiento->account->name : 'Desconocido') . ')',
                        'afecta_stock' => false,
                        'account' => $toAccountName ? [
                            'id' => $toAccountId,
                            'name' => $toAccountName,
                        ] : null,
                        'metadata' => $movimiento->metadata,
                    ];

                    continue;
                }

                $movimientosFormateados[] = [
                    'id' => $movimiento->id,
                    'fecha' => $movimiento->entry_date->format('Y-m-d'),
                    'fecha_formateada' => $movimiento->entry_date->format('d/m/Y'),
                    'movimiento_tipo' => $movimiento->type === 'income' ? 'entrada' : 'salida',
                    'concepto_tipo' => $conceptoTipo,
                    'concepto' => $concepto,
                    'codigo_aux' => null,
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
     * Display a listing of kardex movements aggregated by product/service and month.
     */
    public function indexByProduct(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            // Establecer rango de fechas por defecto (año actual para ver por meses)
            if (!$startDate || !$endDate) {
                $startDate = now()->startOfYear()->format('Y-m-d');
                $endDate = now()->endOfYear()->format('Y-m-d');
            }

            // Construir query con filtros de fecha
            $query = FinancialMovement::with(['movable'])
                ->whereBetween('entry_date', [$startDate, $endDate])
                ->orderBy('entry_date', 'desc');

            $movimientos = $query->get();

            // Cargar morph relations para ventas, compras y órdenes de trabajo
            $movimientos->loadMorph('movable', [
                'App\Models\Sales\Sale' => ['details.product'],
                'App\Models\Sale\Sale' => ['details.product'],
                'App\Models\Finance\PaymentDistribution' => ['financeRecord'],
                'App\Models\WorkOrder\WorkOrder' => ['items.product']
            ]);

            $processedProductInvoiceNumbers = [];
            $aggregated = [];

            foreach ($movimientos as $movimiento) {
                $monthKey = $movimiento->entry_date->format('Y-m');
                $monthName = $this->getMonthNameInSpanish((int) $movimiento->entry_date->format('n')) . ' ' . $movimiento->entry_date->format('Y');

                // Caso 1: Ventas por artículo (Sale)
                if ($movimiento->movable_type === 'App\Models\Sales\Sale' || $movimiento->movable_type === 'App\Models\Sale\Sale') {
                    $sale = $movimiento->movable;
                    if ($sale && $sale->relationLoaded('details') && $sale->details->isNotEmpty()) {
                        foreach ($sale->details as $detail) {
                            $product = $detail->product;
                            $sku = $product ? $product->sku : null;
                            $codeAux = $product ? $product->code_aux : null;
                            $description = $detail->description;

                            // Filtro de búsqueda en memoria
                            if ($search) {
                                $matches = stripos($description, $search) !== false ||
                                    ($sku && stripos($sku, $search) !== false) ||
                                    ($codeAux && stripos($codeAux, $search) !== false);
                                if (!$matches) {
                                    continue;
                                }
                            }

                            $tipo = ($product && $product->item_type == 2) ? 'servicio' : 'producto';
                            if (!$product) {
                                $tipo = 'servicio';
                            }

                            $productId = $detail->product_id ?: 0;
                            $key = $monthKey . '_' . $tipo . '_' . $productId . '_' . md5($description);

                            if (!isset($aggregated[$key])) {
                                $aggregated[$key] = [
                                    'month_key' => $monthKey,
                                    'month_name' => $monthName,
                                    'item_id' => $productId,
                                    'sku' => $sku,
                                    'code_aux' => $codeAux,
                                    'description' => $description,
                                    'tipo' => $tipo,
                                    'cantidad_vendida' => 0.0,
                                    'monto_vendido' => 0.0,
                                    'cantidad_comprada' => 0.0,
                                    'monto_comprado' => 0.0,
                                ];
                            }

                            $aggregated[$key]['cantidad_vendida'] += (float) $detail->quantity;
                            $aggregated[$key]['monto_vendido'] += (float) $detail->total;
                        }
                    }
                }

                // Caso 2: Órdenes de trabajo (WorkOrder) si están directamente en movimientos
                if ($movimiento->movable_type === 'App\Models\WorkOrder\WorkOrder') {
                    $workOrder = $movimiento->movable;
                    if ($workOrder && $workOrder->relationLoaded('items') && $workOrder->items->isNotEmpty()) {
                        foreach ($workOrder->items as $item) {
                            $product = $item->product;
                            $sku = $product ? $product->sku : null;
                            $codeAux = $product ? $product->code_aux : null;
                            $description = $item->description;

                            // Filtro de búsqueda en memoria
                            if ($search) {
                                $matches = stripos($description, $search) !== false ||
                                    ($sku && stripos($sku, $search) !== false) ||
                                    ($codeAux && stripos($codeAux, $search) !== false);
                                if (!$matches) {
                                    continue;
                                }
                            }

                            $tipo = ($product && $product->item_type == 2) ? 'servicio' : 'producto';
                            if (!$product) {
                                $tipo = 'servicio';
                            }

                            $productId = $item->product_id ?: 0;
                            $key = $monthKey . '_' . $tipo . '_' . $productId . '_' . md5($description);

                            if (!isset($aggregated[$key])) {
                                $aggregated[$key] = [
                                    'month_key' => $monthKey,
                                    'month_name' => $monthName,
                                    'item_id' => $productId,
                                    'sku' => $sku,
                                    'code_aux' => $codeAux,
                                    'description' => $description,
                                    'tipo' => $tipo,
                                    'cantidad_vendida' => 0.0,
                                    'monto_vendido' => 0.0,
                                    'cantidad_comprada' => 0.0,
                                    'monto_comprado' => 0.0,
                                ];
                            }

                            $aggregated[$key]['cantidad_vendida'] += (float) $item->quantity;
                            $aggregated[$key]['monto_vendido'] += (float) $item->subtotal;
                        }
                    }
                }

                // Caso 3: Compras por artículo (PaymentDistribution -> Invoice -> InvoiceItem)
                if ($movimiento->movable_type === 'App\Models\Finance\PaymentDistribution') {
                    $distribution = $movimiento->movable;
                    if ($distribution && $distribution->financeRecord) {
                        $invoiceNumber = $distribution->financeRecord->invoice_number;

                        if (!in_array($invoiceNumber, $processedProductInvoiceNumbers)) {
                            $processedProductInvoiceNumbers[] = $invoiceNumber;

                            $invoice = \App\Models\Invoice\Invoice::with('invoice_items')
                                ->where('invoice_number', $invoiceNumber)
                                ->first();

                            if ($invoice && $invoice->invoice_items->isNotEmpty()) {
                                foreach ($invoice->invoice_items as $item) {
                                    $sku = $item->code;
                                    $description = $item->description;

                                    $product = \App\Models\Product\Product::where('sku', $sku)->first();
                                    $codeAux = $product ? $product->code_aux : null;

                                    // Filtro de búsqueda en memoria
                                    if ($search) {
                                        $matches = stripos($description, $search) !== false ||
                                            ($sku && stripos($sku, $search) !== false) ||
                                            ($codeAux && stripos($codeAux, $search) !== false);
                                        if (!$matches) {
                                            continue;
                                        }
                                    }

                                    $tipo = ($product && $product->item_type == 2) ? 'servicio' : 'producto';
                                    $productId = $product ? $product->id : 0;
                                    $key = $monthKey . '_' . $tipo . '_' . $productId . '_' . md5($description);

                                    if (!isset($aggregated[$key])) {
                                        $aggregated[$key] = [
                                            'month_key' => $monthKey,
                                            'month_name' => $monthName,
                                            'item_id' => $productId,
                                            'sku' => $sku,
                                            'code_aux' => $codeAux,
                                            'description' => $description,
                                            'tipo' => $tipo,
                                            'cantidad_vendida' => 0.0,
                                            'monto_vendido' => 0.0,
                                            'cantidad_comprada' => 0.0,
                                            'monto_comprado' => 0.0,
                                        ];
                                    }

                                    $aggregated[$key]['cantidad_comprada'] += (float) $item->quantity;
                                    $aggregated[$key]['monto_comprado'] += (float) $item->total;
                                }
                            }
                        }
                    }
                }
            }

            // Ordenar por mes descendente, luego por monto vendido descendente
            usort($aggregated, function ($a, $b) {
                if ($a['month_key'] === $b['month_key']) {
                    return $b['monto_vendido'] <=> $a['monto_vendido'];
                }
                return strcmp($b['month_key'], $a['month_key']);
            });

            // Agrupar por mes
            $groupedByMonth = [];
            foreach ($aggregated as $item) {
                $groupedByMonth[$item['month_name']][] = $item;
            }

            return response()->json([
                'status' => 200,
                'message' => 'Kardex por producto obtenido exitosamente',
                'data' => [
                    'items' => $aggregated,
                    'items_grouped' => $groupedByMonth,
                    'filtros_aplicados' => [
                        'search' => $search,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ],
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'message' => 'Error al obtener el kardex por producto',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    private function getMonthNameInSpanish(int $monthNum)
    {
        $months = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        ];
        return $months[$monthNum] ?? '';
    }

    /**
     * Determinar el tipo de concepto basado en el movable_type
     */
    private function getConceptoTipo($movableType)
    {
        $mapping = [
            'App\Models\Sale\Sale' => 'venta_producto',
            'App\Models\Sales\Sale' => 'venta_producto',
            'App\Models\WorkOrder\WorkOrder' => 'venta_servicio',
            'App\Models\Purchase\Purchase' => 'compra_inventario',
            'App\Models\Employee\EmployeeExpense' => 'pago_sueldo',
            'App\Models\Employee\EmployeePayment' => 'pago_sueldo',
            'App\Models\Employee\EmployeeAdvance' => 'adelanto',
            'App\Models\Finance\FinanceRecord' => 'gasto_general',
            'App\Models\Finance\PaymentDistribution' => 'compra_inventario',
            'App\Models\Sales\ProductReturn' => 'devolucion',
        ];

        return $mapping[$movableType] ?? 'gasto_general';
    }

    /**
     * Determinar el concepto a mostrar en la columna de concepto del Kardex
     */
    private function getConceptoDisplay($movableType, $description = '')
    {
        $mapping = [
            'App\Models\Sale\Sale' => 'VENTA',
            'App\Models\Sales\Sale' => 'VENTA',
            'App\Models\WorkOrder\WorkOrder' => 'SERVICIO',
            'App\Models\Purchase\Purchase' => 'COMPRA',
            'App\Models\Employee\EmployeeExpense' => 'NÓMINA',
            'App\Models\Employee\EmployeePayment' => 'NÓMINA',
            'App\Models\Employee\EmployeeAdvance' => 'ADELANTO',
            'App\Models\Finance\FinanceRecord' => 'GASTO',
            'App\Models\Finance\PaymentDistribution' => 'COMPRA',
            'App\Models\Sales\ProductReturn' => 'DEVOLUCIÓN',
        ];

        $concepto = $mapping[$movableType] ?? 'GASTO';

        // Si es un gasto general y la descripción menciona algo como logística, lo clasificamos directamente
        if ($concepto === 'GASTO' && !empty($description)) {
            if (stripos($description, 'logística') !== false || stripos($description, 'logistica') !== false) {
                return 'LOGÍSTICA';
            }
        }

        return $concepto;
    }
}
