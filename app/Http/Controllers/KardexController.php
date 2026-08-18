<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinancialMovement;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

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

    /**
     * Kardex integral por Cliente y Vehículo (Búsqueda por Placa, RUC, Nombre)
     */
    public function indexByClientAndVehicle(Request $request)
    {
        try {
            $clientId = $request->get('client_id');
            $vehicleId = $request->get('vehicle_id');
            $plate = $request->get('plate');
            $search = $request->get('search');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $documentType = $request->get('document_type', 'all');
            $paymentStatus = $request->get('payment_status', 'all');
            $perPage = (int) $request->get('per_page', 20);

            // Base query de ventas / transacciones
            $query = \App\Models\Sales\Sale::with([
                'client',
                'vehicle',
                'details.product',
                'financeRecord.paymentDistributions',
            ]);

            // Filtro por Cliente específico
            if ($clientId) {
                $query->where('client_id', $clientId);
            }

            // Filtro por Vehículo específico
            if ($vehicleId) {
                $query->where('vehicle_id', $vehicleId);
            }

            // Filtro por Placa específica
            if ($plate) {
                $query->whereHas('vehicle', function ($vq) use ($plate) {
                    $vq->where('license_plate', 'LIKE', "%{$plate}%");
                });
            }

            // Filtro por Rango de Fechas
            if ($startDate && $endDate) {
                $query->where(function ($dq) use ($startDate, $endDate) {
                    $dq->whereBetween('service_date', [$startDate, $endDate])
                        ->orWhere(function ($sub) use ($startDate, $endDate) {
                            $sub->whereNull('service_date')
                                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                        });
                });
            }

            // Filtro por Tipo de Comprobante
            if ($documentType && $documentType !== 'all') {
                $query->where('document_type', $documentType);
            }

            // Filtro por Estado de Pago
            if ($paymentStatus && $paymentStatus !== 'all') {
                $query->where('payment_status', $paymentStatus);
            }

            // Búsqueda textual amplia
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('document_number', 'LIKE', "%{$search}%")
                        ->orWhere('work_order_number', 'LIKE', "%{$search}%")
                        ->orWhere('observations', 'LIKE', "%{$search}%")
                        ->orWhereHas('client', function ($cq) use ($search) {
                            $cq->where('name', 'LIKE', "%{$search}%")
                                ->orWhere('surname', 'LIKE', "%{$search}%")
                                ->orWhere('full_name', 'LIKE', "%{$search}%")
                                ->orWhere('n_document', 'LIKE', "%{$search}%")
                                ->orWhere('phone', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('vehicle', function ($vq) use ($search) {
                            $vq->where('license_plate', 'LIKE', "%{$search}%")
                                ->orWhere('brand', 'LIKE', "%{$search}%")
                                ->orWhere('model', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('details', function ($dq) use ($search) {
                            $dq->where('description', 'LIKE', "%{$search}%");
                        });
                });
            }

            $calculateSalePayment = function ($sale) {
                $saleTotal = (float) $sale->total;

                if ($sale->payment_status === 'paid') {
                    return [
                        'paid' => $saleTotal,
                        'due' => 0.0,
                    ];
                }

                $paid = 0.0;
                if ($sale->financeRecord) {
                    if ($sale->financeRecord->paymentDistributions && $sale->financeRecord->paymentDistributions->isNotEmpty()) {
                        $paid = (float) $sale->financeRecord->paymentDistributions->sum('amount');
                    } elseif ($sale->financeRecord->amount !== null && (float)$sale->financeRecord->amount > 0) {
                        $paid = (float) $sale->financeRecord->amount;
                    }
                }

                $due = max(0.0, $saleTotal - $paid);
                return [
                    'paid' => $paid,
                    'due' => $due,
                ];
            };

            // Calcular Métricas y KPIs sobre el query filtrado
            $statsQuery = clone $query;
            $allFilteredSales = $statsQuery->get();

            $totalFacturado = 0.0;
            $totalPagado = 0.0;
            $saldoPendiente = 0.0;
            $totalTransacciones = $allFilteredSales->count();

            // Calcular desglose de Repuestos vs Servicios y último kilometraje
            $totalRepuestos = 0.0;
            $totalServicios = 0.0;
            $repuestosCount = 0;
            $serviciosCount = 0;
            $maxMileage = null;

            foreach ($allFilteredSales as $sale) {
                $pInfo = $calculateSalePayment($sale);
                $totalFacturado += (float) $sale->total;
                $totalPagado += $pInfo['paid'];
                $saldoPendiente += $pInfo['due'];

                if ($sale->mileage !== null && ($maxMileage === null || (int)$sale->mileage > (int)$maxMileage)) {
                    $maxMileage = (int) $sale->mileage;
                }
                foreach ($sale->details as $detail) {
                    $isService = ($detail->product && $detail->product->item_type == 2) || (!$detail->product && (stripos($detail->description, 'servicio') !== false || stripos($detail->description, 'mano de obra') !== false || stripos($detail->description, 'alineacion') !== false || stripos($detail->description, 'balanceo') !== false));
                    if ($isService) {
                        $totalServicios += (float) $detail->total;
                        $serviciosCount++;
                    } else {
                        $totalRepuestos += (float) $detail->total;
                        $repuestosCount++;
                    }
                }
            }

            $vehicleBrands = config('vehicle_brands', []);
            $resolveBrand = function ($brandVal) use ($vehicleBrands) {
                if ($brandVal !== null && isset($vehicleBrands[$brandVal])) {
                    return $vehicleBrands[$brandVal];
                }
                return $brandVal ?: '';
            };

            // Obtener datos del Cliente seleccionado si existe
            $selectedClient = null;
            if ($clientId) {
                $clientModel = \App\Models\Client\Client::with(['directVehicles'])->find($clientId);
                if ($clientModel) {
                    $selectedClient = [
                        'id' => $clientModel->id,
                        'name' => $clientModel->name,
                        'surname' => $clientModel->surname,
                        'full_name' => $clientModel->full_name ?: trim($clientModel->name . ' ' . $clientModel->surname),
                        'n_document' => $clientModel->n_document,
                        'type_document' => $clientModel->type_document,
                        'phone' => $clientModel->phone,
                        'email' => $clientModel->email,
                        'address' => $clientModel->address,
                        'vehicles' => $clientModel->directVehicles->map(function ($v) use ($resolveBrand) {
                            return [
                                'id' => $v->id,
                                'license_plate' => $v->license_plate,
                                'brand' => $resolveBrand($v->brand),
                                'model' => $v->model,
                                'year' => $v->year,
                                'color' => $v->color,
                            ];
                        }),
                    ];
                }
            }

            // Obtener datos del Vehículo seleccionado si existe
            $selectedVehicle = null;
            if ($vehicleId || $plate) {
                $vQuery = \App\Models\Vehicles\Vehicle::with(['client']);
                if ($vehicleId) {
                    $vQuery->where('id', $vehicleId);
                } elseif ($plate) {
                    $vQuery->where('license_plate', $plate);
                }
                $vehicleModel = $vQuery->first();
                if ($vehicleModel) {
                    $selectedVehicle = [
                        'id' => $vehicleModel->id,
                        'license_plate' => $vehicleModel->license_plate,
                        'brand' => $resolveBrand($vehicleModel->brand),
                        'model' => $vehicleModel->model,
                        'year' => $vehicleModel->year,
                        'color' => $vehicleModel->color,
                        'vehicle_type' => $vehicleModel->vehicle_type,
                        'description' => $vehicleModel->description,
                        'last_mileage' => $maxMileage,
                        'client' => $vehicleModel->client ? [
                            'id' => $vehicleModel->client->id,
                            'full_name' => $vehicleModel->client->full_name ?: trim($vehicleModel->client->name . ' ' . $vehicleModel->client->surname),
                            'n_document' => $vehicleModel->client->n_document,
                            'phone' => $vehicleModel->client->phone,
                        ] : null,
                    ];

                    // Si no había cliente seleccionado, usar el dueño del vehículo
                    if (!$selectedClient && $vehicleModel->client) {
                        $selectedClient = [
                            'id' => $vehicleModel->client->id,
                            'name' => $vehicleModel->client->name,
                            'surname' => $vehicleModel->client->surname,
                            'full_name' => $vehicleModel->client->full_name ?: trim($vehicleModel->client->name . ' ' . $vehicleModel->client->surname),
                            'n_document' => $vehicleModel->client->n_document,
                            'type_document' => $vehicleModel->client->type_document,
                            'phone' => $vehicleModel->client->phone,
                            'email' => $vehicleModel->client->email,
                            'address' => $vehicleModel->client->address,
                        ];
                    }
                }
            }

            // Paginar los resultados ordenados por fecha descendente
            $paginated = $query->orderByRaw('COALESCE(service_date, created_at) DESC')
                ->orderByDesc('id')
                ->paginate($perPage);

            // Formatear transacciones
            $transactions = $paginated->getCollection()->map(function ($sale) use ($resolveBrand, $calculateSalePayment) {
                $dateStr = $sale->service_date ? $sale->service_date->format('Y-m-d') : $sale->created_at->format('Y-m-d');
                $dateFormatted = $sale->service_date ? $sale->service_date->format('d/m/Y') : $sale->created_at->format('d/m/Y');

                $pInfo = $calculateSalePayment($sale);

                return [
                    'id' => $sale->id,
                    'document_type' => $sale->document_type,
                    'document_number' => $sale->document_number ?: ('#' . $sale->id),
                    'work_order_id' => $sale->work_order_id,
                    'work_order_number' => $sale->work_order_number,
                    'date' => $dateStr,
                    'date_formatted' => $dateFormatted,
                    'mileage' => $sale->mileage,
                    'subtotal' => (float) $sale->subtotal,
                    'tax_amount' => (float) $sale->tax_amount,
                    'total' => (float) $sale->total,
                    'paid_amount' => $pInfo['paid'],
                    'due_amount' => $pInfo['due'],
                    'payment_status' => $sale->payment_status,
                    'payment_method' => $sale->payment_method ?: 'Efectivo',
                    'observations' => $sale->observations,
                    'pdf_path' => $sale->pdf_path,
                    'client' => $sale->client ? [
                        'id' => $sale->client->id,
                        'full_name' => $sale->client->full_name ?: trim($sale->client->name . ' ' . $sale->client->surname),
                        'n_document' => $sale->client->n_document,
                        'phone' => $sale->client->phone,
                    ] : null,
                    'vehicle' => $sale->vehicle ? [
                        'id' => $sale->vehicle->id,
                        'license_plate' => $sale->vehicle->license_plate,
                        'brand' => $resolveBrand($sale->vehicle->brand),
                        'model' => $sale->vehicle->model,
                        'year' => $sale->vehicle->year,
                        'color' => $sale->vehicle->color,
                    ] : null,
                    'details' => $sale->details->map(function ($detail) {
                        $isService = ($detail->product && $detail->product->item_type == 2) || (!$detail->product && (stripos($detail->description, 'servicio') !== false || stripos($detail->description, 'mano de obra') !== false || stripos($detail->description, 'alineacion') !== false || stripos($detail->description, 'balanceo') !== false));
                        $quantity = (float) $detail->quantity;
                        $unitPrice = (float) ($detail->price ?? $detail->unit_price ?? ($quantity > 0 ? $detail->total / $quantity : 0));
                        $discount = (float) ($detail->discount ?? 0);
                        $subtotal = (float) ($detail->subtotal ?? (($unitPrice * $quantity) - $discount));
                        $taxAmount = (float) ($detail->tax_value ?? $detail->tax_amount ?? 0);
                        $total = (float) ($detail->total ?? ($subtotal + $taxAmount));

                        return [
                            'id' => $detail->id,
                            'product_id' => $detail->product_id,
                            'sku' => $detail->product ? $detail->product->sku : null,
                            'description' => $detail->description,
                            'tipo' => $isService ? 'servicio' : 'repuesto',
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'discount' => $discount,
                            'subtotal' => $subtotal,
                            'tax_amount' => $taxAmount,
                            'total' => $total,
                        ];
                    }),
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'transactions' => $transactions,
                    'pagination' => [
                        'total' => $paginated->total(),
                        'per_page' => $paginated->perPage(),
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                    ],
                    'metrics' => [
                        'total_facturado' => $totalFacturado,
                        'total_pagado' => $totalPagado,
                        'saldo_pendiente' => $saldoPendiente,
                        'total_transacciones' => $totalTransacciones,
                        'total_repuestos' => $totalRepuestos,
                        'total_servicios' => $totalServicios,
                        'repuestos_count' => $repuestosCount,
                        'servicios_count' => $serviciosCount,
                        'ultimo_kilometraje' => $maxMileage,
                        'promedio_visita' => $totalTransacciones > 0 ? round($totalFacturado / $totalTransacciones, 2) : 0.0,
                    ],
                    'client' => $selectedClient,
                    'vehicle' => $selectedVehicle,
                ],
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Error en indexByClientAndVehicle: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener el kardex por cliente/vehículo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generar Reporte Completo de Kardex Cliente & Vehículo en PDF
     */
    public function generateClientVehicleReportPDF(Request $request)
    {
        try {
            $clientId = $request->get('client_id');
            $vehicleId = $request->get('vehicle_id');
            $plate = $request->get('plate');
            $search = $request->get('search');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $documentType = $request->get('document_type', 'all');
            $paymentStatus = $request->get('payment_status', 'all');

            $query = \App\Models\Sales\Sale::with([
                'client',
                'vehicle',
                'details.product',
                'financeRecord.paymentDistributions',
            ]);

            if ($clientId) {
                $query->where('client_id', $clientId);
            }

            if ($vehicleId) {
                $query->where('vehicle_id', $vehicleId);
            }

            if ($plate) {
                $query->whereHas('vehicle', function ($vq) use ($plate) {
                    $vq->where('license_plate', 'LIKE', "%{$plate}%");
                });
            }

            if ($startDate && $endDate) {
                $query->where(function ($dq) use ($startDate, $endDate) {
                    $dq->whereBetween('service_date', [$startDate, $endDate])
                        ->orWhere(function ($sub) use ($startDate, $endDate) {
                            $sub->whereNull('service_date')
                                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                        });
                });
                $dateRangeText = date('d/m/Y', strtotime($startDate)) . ' al ' . date('d/m/Y', strtotime($endDate));
            } else {
                $dateRangeText = 'Todo el historial';
            }

            if ($documentType && $documentType !== 'all') {
                $query->where('document_type', $documentType);
            }

            if ($paymentStatus && $paymentStatus !== 'all') {
                $query->where('payment_status', $paymentStatus);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('document_number', 'LIKE', "%{$search}%")
                        ->orWhere('work_order_number', 'LIKE', "%{$search}%")
                        ->orWhere('observations', 'LIKE', "%{$search}%")
                        ->orWhereHas('client', function ($cq) use ($search) {
                            $cq->where('name', 'LIKE', "%{$search}%")
                                ->orWhere('surname', 'LIKE', "%{$search}%")
                                ->orWhere('full_name', 'LIKE', "%{$search}%")
                                ->orWhere('n_document', 'LIKE', "%{$search}%")
                                ->orWhere('phone', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('vehicle', function ($vq) use ($search) {
                            $vq->where('license_plate', 'LIKE', "%{$search}%")
                                ->orWhere('brand', 'LIKE', "%{$search}%")
                                ->orWhere('model', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('details', function ($dq) use ($search) {
                            $dq->where('description', 'LIKE', "%{$search}%");
                        });
                });
            }

            $allSales = $query->orderByRaw('COALESCE(service_date, created_at) DESC')
                ->orderByDesc('id')
                ->get();

            $calculateSalePayment = function ($sale) {
                $saleTotal = (float) $sale->total;

                if ($sale->payment_status === 'paid') {
                    return [
                        'paid' => $saleTotal,
                        'due' => 0.0,
                    ];
                }

                $paid = 0.0;
                if ($sale->financeRecord) {
                    if ($sale->financeRecord->paymentDistributions && $sale->financeRecord->paymentDistributions->isNotEmpty()) {
                        $paid = (float) $sale->financeRecord->paymentDistributions->sum('amount');
                    } elseif ($sale->financeRecord->amount !== null && (float)$sale->financeRecord->amount > 0) {
                        $paid = (float) $sale->financeRecord->amount;
                    }
                }

                $due = max(0.0, $saleTotal - $paid);
                return [
                    'paid' => $paid,
                    'due' => $due,
                ];
            };

            $totalFacturado = 0.0;
            $totalPagado = 0.0;
            $saldoPendiente = 0.0;
            $totalTransacciones = $allSales->count();

            $totalRepuestos = 0.0;
            $totalServicios = 0.0;
            $maxMileage = null;

            $vehicleBrands = config('vehicle_brands', []);
            $resolveBrand = function ($brandVal) use ($vehicleBrands) {
                if ($brandVal !== null && isset($vehicleBrands[$brandVal])) {
                    return $vehicleBrands[$brandVal];
                }
                return $brandVal ?: '';
            };

            foreach ($allSales as $sale) {
                $pInfo = $calculateSalePayment($sale);
                $totalFacturado += (float) $sale->total;
                $totalPagado += $pInfo['paid'];
                $saldoPendiente += $pInfo['due'];

                if ($sale->mileage !== null && ($maxMileage === null || (int)$sale->mileage > (int)$maxMileage)) {
                    $maxMileage = (int) $sale->mileage;
                }
                foreach ($sale->details as $detail) {
                    $isService = ($detail->product && $detail->product->item_type == 2) || (!$detail->product && (stripos($detail->description, 'servicio') !== false || stripos($detail->description, 'mano de obra') !== false || stripos($detail->description, 'alineacion') !== false || stripos($detail->description, 'balanceo') !== false));
                    if ($isService) {
                        $totalServicios += (float) $detail->total;
                    } else {
                        $totalRepuestos += (float) $detail->total;
                    }
                }
            }

            $selectedClient = null;
            if ($clientId) {
                $clientModel = \App\Models\Client\Client::find($clientId);
                if ($clientModel) {
                    $selectedClient = [
                        'full_name' => $clientModel->full_name ?: trim($clientModel->name . ' ' . $clientModel->surname),
                        'n_document' => $clientModel->n_document,
                        'phone' => $clientModel->phone,
                    ];
                }
            }

            $selectedVehicle = null;
            if ($vehicleId || $plate) {
                $vQuery = \App\Models\Vehicles\Vehicle::query();
                if ($vehicleId) $vQuery->where('id', $vehicleId);
                elseif ($plate) $vQuery->where('license_plate', $plate);
                $vehicleModel = $vQuery->first();
                if ($vehicleModel) {
                    $selectedVehicle = [
                        'license_plate' => $vehicleModel->license_plate,
                        'brand' => $resolveBrand($vehicleModel->brand),
                        'model' => $vehicleModel->model,
                        'year' => $vehicleModel->year,
                        'color' => $vehicleModel->color,
                        'last_mileage' => $maxMileage,
                    ];
                }
            }

            $transactions = $allSales->map(function ($sale) use ($resolveBrand, $calculateSalePayment) {
                $dateFormatted = $sale->service_date ? $sale->service_date->format('d/m/Y') : $sale->created_at->format('d/m/Y');
                $pInfo = $calculateSalePayment($sale);
                return [
                    'id' => $sale->id,
                    'document_type' => $sale->document_type,
                    'document_number' => $sale->document_number ?: ('#' . $sale->id),
                    'work_order_number' => $sale->work_order_number,
                    'date_formatted' => $dateFormatted,
                    'mileage' => $sale->mileage,
                    'total' => (float) $sale->total,
                    'paid_amount' => $pInfo['paid'],
                    'due_amount' => $pInfo['due'],
                    'payment_status' => $sale->payment_status,
                    'client' => $sale->client ? [
                        'full_name' => $sale->client->full_name ?: trim($sale->client->name . ' ' . $sale->client->surname),
                        'n_document' => $sale->client->n_document,
                    ] : null,
                    'vehicle' => $sale->vehicle ? [
                        'license_plate' => $sale->vehicle->license_plate,
                        'brand' => $resolveBrand($sale->vehicle->brand),
                        'model' => $sale->vehicle->model,
                    ] : null,
                    'details' => $sale->details->map(function ($detail) {
                        $isService = ($detail->product && $detail->product->item_type == 2) || (!$detail->product && (stripos($detail->description, 'servicio') !== false || stripos($detail->description, 'mano de obra') !== false || stripos($detail->description, 'alineacion') !== false || stripos($detail->description, 'balanceo') !== false));
                        $quantity = (float) $detail->quantity;
                        $unitPrice = (float) ($detail->price ?? $detail->unit_price ?? ($quantity > 0 ? $detail->total / $quantity : 0));
                        $subtotal = (float) ($detail->subtotal ?? ($unitPrice * $quantity));
                        return [
                            'description' => $detail->description,
                            'tipo' => $isService ? 'servicio' : 'repuesto',
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'total' => (float) $detail->total,
                        ];
                    }),
                ];
            });

            $metrics = [
                'total_facturado' => $totalFacturado,
                'total_pagado' => $totalPagado,
                'saldo_pendiente' => $saldoPendiente,
                'total_transacciones' => $totalTransacciones,
                'total_repuestos' => $totalRepuestos,
                'total_servicios' => $totalServicios,
            ];

            $pdf = Pdf::loadView('kardex.pdf_kardex_client_vehicle', compact(
                'transactions',
                'metrics',
                'selectedClient',
                'selectedVehicle',
                'dateRangeText'
            ))->setPaper('a4', 'landscape');

            return $pdf->stream('Kardex_Cliente_Vehiculo_' . date('Ymd_His') . '.pdf');
        } catch (\Throwable $e) {
            \Log::error('Error al generar PDF de Kardex: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => 'Error al generar el PDF de Kardex: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Selector de Clientes con autocompletado y conteo de vehículos/compras
     */
    public function clientsSelector(Request $request)
    {
        try {
            $search = $request->get('search', '');

            $clients = \App\Models\Client\Client::withCount(['directVehicles', 'sales'])
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('surname', 'LIKE', "%{$search}%")
                            ->orWhere('full_name', 'LIKE', "%{$search}%")
                            ->orWhere('n_document', 'LIKE', "%{$search}%")
                            ->orWhere('phone', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->limit(40)
                ->get()
                ->map(function ($client) {
                    return [
                        'id' => $client->id,
                        'full_name' => $client->full_name ?: trim($client->name . ' ' . $client->surname),
                        'name' => $client->name,
                        'surname' => $client->surname,
                        'n_document' => $client->n_document,
                        'phone' => $client->phone,
                        'email' => $client->email,
                        'vehicles_count' => $client->direct_vehicles_count ?? 0,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'clients' => $clients,
            ], 200);
        } catch (\Throwable $e) {
            \Log::error('Error en clientsSelector: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Selector de Vehículos / Placas con autocompletado y dueño
     */
    public function vehiclesSelector(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $vehicleBrands = config('vehicle_brands', []);

            $vehicles = \App\Models\Vehicles\Vehicle::with(['client'])
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('license_plate', 'LIKE', "%{$search}%")
                            ->orWhere('brand', 'LIKE', "%{$search}%")
                            ->orWhere('model', 'LIKE', "%{$search}%")
                            ->orWhere('color', 'LIKE', "%{$search}%")
                            ->orWhereHas('client', function ($cq) use ($search) {
                                $cq->where('name', 'LIKE', "%{$search}%")
                                    ->orWhere('surname', 'LIKE', "%{$search}%")
                                    ->orWhere('full_name', 'LIKE', "%{$search}%")
                                    ->orWhere('n_document', 'LIKE', "%{$search}%");
                            });
                    });
                })
                ->orderBy('license_plate')
                ->limit(40)
                ->get()
                ->map(function ($v) use ($vehicleBrands) {
                    $brandName = (isset($vehicleBrands[$v->brand])) ? $vehicleBrands[$v->brand] : $v->brand;
                    return [
                        'id' => $v->id,
                        'license_plate' => $v->license_plate,
                        'brand' => $brandName,
                        'model' => $v->model,
                        'year' => $v->year,
                        'color' => $v->color,
                        'client' => $v->client ? [
                            'id' => $v->client->id,
                            'full_name' => $v->client->full_name ?: trim($v->client->name . ' ' . $v->client->surname),
                            'n_document' => $v->client->n_document,
                            'phone' => $v->client->phone,
                        ] : null,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'vehicles' => $vehicles,
            ], 200);
        } catch (\Throwable $e) {
            \Log::error('Error en vehiclesSelector: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
