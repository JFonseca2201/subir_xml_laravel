<?php

namespace App\Services\Sales;

use App\Models\Sales\Sale;
use App\Models\Sales\SaleDetail;
use App\Models\Product\Product as ModelsProduct;
use App\Models\WorkOrder\WorkOrder;
use App\Models\Finance\FinanceRecord;
use App\Models\Finance\FinancialMovement;
use App\Models\Finance\Account;
use App\Services\SequenceService;
use App\Services\WorkOrder\WorkOrderSaleSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class SaleUpdateService
{
    public function __construct(
        protected SaleFinanceService $financeService,
        protected SaleReminderService $reminderService
    ) {}

    /**
     * Actualiza una venta o cotización existente.
     */
    public function updateSale(Sale $sale, Request $request): array
    {
        // Regla de seguridad: Si la venta ya está anulada, no debería editarse
        if ($sale->status === 'canceled') {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'No se puede editar una venta que ya ha sido anulada.'
                ]
            ];
        }

        // Regla SRI: Si es factura y ya está autorizada por el SRI, no se puede editar
        if ($sale->document_type === 'invoice' && $sale->sri_status === 'AUTORIZADA') {
            return [
                'status' => 422,
                'data' => [
                    'success' => false,
                    'message' => 'Esta factura ya ha sido autorizada por el SRI y no puede ser modificada.'
                ]
            ];
        }

        // Regla: Si la cotización ya fue convertida, está bloqueada
        if ($sale->document_type === 'quote' && $sale->is_converted) {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Esta cotización ya fue convertida en venta/factura y no puede modificarse.'
                ]
            ];
        }

        // Reglas de conversión de tipo de documento:
        // 1. Cotización (quote) puede convertirse a Nota de Venta (sale_note) o Factura (invoice).
        // 2. Nota de Venta (sale_note) puede convertirse a Factura (invoice).
        // 3. Factura (invoice) NO se puede convertir a Nota de Venta ni Cotización.
        // 4. Nota de Venta (sale_note) NO se puede convertir a Cotización.
        if ($request->has('document_type') && $request->document_type !== $sale->document_type) {
            $newType = $request->document_type;
            $currentType = $sale->document_type;

            if ($currentType === 'invoice') {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => 'Una factura no puede convertirse en nota de venta ni en cotización.'
                    ]
                ];
            }

            if ($currentType === 'sale_note' && $newType === 'quote') {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => 'Una nota de venta no puede convertirse en cotización.'
                    ]
                ];
            }
        }

        $wasQuote = $sale->document_type === 'quote';
        $wasDraft = $sale->status === 'draft';
        $isNowSale = $request->has('document_type') && $request->document_type !== 'quote';
        $isDraft = $request->boolean('is_draft');
        $isFinishingDraft = $wasDraft && !$isDraft;

        // Validar pagos distribuidos solo si no es cotización y no es borrador
        $docType = $request->has('document_type') ? $request->document_type : $sale->document_type;
        $clientId = $request->has('client_id') ? $request->client_id : $sale->client_id;

        // Validaciones fiscales para Facturación Electrónica SRI
        if ($docType === 'invoice' && !$isDraft && $clientId) {
            $client = \App\Models\Client\Client::find($clientId);
            if ($client) {
                $docNum = trim($client->n_document ?? '');
                $clientName = strtoupper(trim(($client->name ?? '') . ' ' . ($client->surname ?? '')));
                $isFinalConsumer = in_array($docNum, ['9999999999999', '9999999999']) || str_contains($clientName, 'CONSUMIDOR FINAL');

                $expectedTotal = $sale->total;
                if ($request->has('items')) {
                    $subtotal = 0;
                    foreach ($request->items as $item) {
                        $subtotal += ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0);
                    }
                    $taxAmount = $subtotal * 0.15;
                    $expectedTotal = $subtotal + $taxAmount;
                }

                if ($isFinalConsumer && (float)$expectedTotal >= 50.00) {
                    return [
                        'status' => 422,
                        'data' => [
                            'success' => false,
                            'message' => 'Por normativa del SRI, no se pueden emitir Facturas a Consumidor Final por montos iguales o superiores a $50.00.',
                            'error' => 'final_consumer_limit_exceeded'
                        ]
                    ];
                }

                if (!$isFinalConsumer) {
                    if (!ctype_digit($docNum) || !in_array(strlen($docNum), [10, 13])) {
                        return [
                            'status' => 422,
                            'data' => [
                                'success' => false,
                                'message' => "La identificación del cliente ($docNum) no es válida para facturación electrónica SRI. Debe tener 10 dígitos (cédula) o 13 dígitos (RUC).",
                                'error' => 'invalid_tax_identification'
                            ]
                        ];
                    }
                }
            }
        }
        if ($docType !== 'quote' && !$isDraft) {
            $hasDistributions = $request->has('payment_distributions');
            $isCredited = $request->has('is_credited') ? $request->boolean('is_credited') : $sale->is_credited;

            // Recalcular el total esperado de los items
            $finalTotal = $sale->total;
            if ($request->has('items')) {
                $subtotal = 0;
                foreach ($request->items as $item) {
                    $subtotal += ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0);
                }
                $taxAmount = $docType === 'invoice' ? $subtotal * 0.15 : 0;
                $finalTotal = $subtotal + $taxAmount;
            }

            if ($hasDistributions) {
                $distributions = $request->payment_distributions;
                if ((!is_array($distributions) || count($distributions) === 0) && !$isCredited) {
                    return [
                        'status' => 400,
                        'data' => [
                            'success' => false,
                            'message' => 'Debe registrar al menos un pago para la venta.',
                            'error' => 'validation_error'
                        ]
                    ];
                }

                $totalDist = (is_array($distributions) && count($distributions) > 0) ? array_sum(array_column($distributions, 'amount')) : 0;
                if ($totalDist <= 0 && !$isCredited) {
                    return [
                        'status' => 400,
                        'data' => [
                            'success' => false,
                            'message' => 'Debe registrar al menos un pago para la venta.',
                            'error' => 'validation_error'
                        ]
                    ];
                }

                if ($totalDist > $finalTotal + 0.01) {
                    return [
                        'status' => 400,
                        'data' => [
                            'success' => false,
                            'message' => 'La suma de los pagos no puede ser mayor al total.',
                            'error' => 'validation_error'
                        ]
                    ];
                }

                if (abs($totalDist - $finalTotal) <= 0.01) {
                    $paymentStatus = 'paid';
                } elseif ($totalDist > 0) {
                    $paymentStatus = 'partial';
                } else {
                    $paymentStatus = 'pending';
                }
                $request->merge(['payment_status' => $paymentStatus]);
            } else {
                if ($request->payment_status === 'pending') {
                    $totalDist = 0;
                } else {
                    $totalDist = $sale->financeRecord ? $sale->financeRecord->paymentDistributions->sum('amount') : 0;
                }
                if ($totalDist <= 0 && !$isCredited) {
                    return [
                        'status' => 400,
                        'data' => [
                            'success' => false,
                            'message' => 'Debe registrar al menos un pago para la venta.',
                            'error' => 'validation_error'
                        ]
                    ];
                }

                if ($totalDist > $finalTotal + 0.01) {
                    return [
                        'status' => 400,
                        'data' => [
                            'success' => false,
                            'message' => 'La suma de los pagos no puede ser mayor al total.',
                            'error' => 'validation_error'
                        ]
                    ];
                }

                if (abs($totalDist - $finalTotal) <= 0.01) {
                    $paymentStatus = 'paid';
                } elseif ($totalDist > 0) {
                    $paymentStatus = 'partial';
                } else {
                    $paymentStatus = 'pending';
                }
                $request->merge(['payment_status' => $paymentStatus]);
            }
        }

        // Validar stock si se convierte a venta o si ya es venta
        if ($isNowSale || ($sale->document_type !== 'quote' && !$isDraft)) {
            if ($request->has('items')) {
                foreach ($request->items as $item) {
                    if (isset($item['product_id']) && $item['product_id']) {
                        $product = ModelsProduct::find($item['product_id']);
                        if ($product && $product->item_type == 1) { // Solo si es Producto Físico
                            $quantityNeeded = $item['quantity'];
                            if (!$wasQuote && !$wasDraft && isset($item['id'])) {
                                $oldDetail = $sale->details->firstWhere('id', $item['id']);
                                if ($oldDetail && $oldDetail->product_id == $item['product_id']) {
                                    $quantityNeeded -= $oldDetail->quantity;
                                }
                            }

                            if ($quantityNeeded > 0 && $product->stock < $quantityNeeded) {
                                return [
                                    'status' => 400,
                                    'data' => [
                                        'success' => false,
                                        'message' => "Stock insuficiente para el producto: {$product->description}. Stock disponible: {$product->stock}, Solicitado adicional: {$quantityNeeded}",
                                        'error' => 'stock_insufficient'
                                    ]
                                ];
                            }
                        }
                    }
                }
            }
        }

        // Validar descuentos y margen (sólo aplica para item_type == 1 / Productos Físicos)
        if ($request->has('items')) {
            foreach ($request->items as $item) {
                if (isset($item['product_id']) && $item['product_id']) {
                    $product = ModelsProduct::find($item['product_id']);
                    if ($product && $product->item_type == 1) {
                        $itemDiscount = (float) ($item['discount'] ?? 0.00);
                        $finalPrice = ($item['quantity'] * $item['price']) - $itemDiscount;
                        $minFinalPrice = $item['quantity'] * ($product->purchase_price ?? 0.00);
                        if ($finalPrice < $minFinalPrice) {
                            return [
                                'status' => 400,
                                'data' => [
                                    'success' => false,
                                    'message' => "El descuento excede el margen permitido para el producto: {$product->description}. El precio final no puede ser menor al costo de compra (\${$product->purchase_price} c/u).",
                                    'error' => 'price_below_cost'
                                ]
                            ];
                        }

                        if ($product->discount_percentage > 0) {
                            $maxAllowedByPct = ($item['quantity'] * $item['price']) * ($product->discount_percentage / 100);
                            if ($itemDiscount > $maxAllowedByPct) {
                                return [
                                    'status' => 400,
                                    'data' => [
                                        'success' => false,
                                        'message' => "El descuento total excede el porcentaje máximo permitido ({$product->discount_percentage}%) para el producto: {$product->description}.",
                                        'error' => 'discount_exceeded'
                                    ]
                                ];
                            }
                        }

                        if ($product->max_discount > 0) {
                            $maxAllowedByVal = $item['quantity'] * $product->max_discount;
                            if ($itemDiscount > $maxAllowedByVal) {
                                return [
                                    'status' => 400,
                                    'data' => [
                                        'success' => false,
                                        'message' => "El descuento total excede el máximo permitido para el producto: {$product->description}.",
                                        'error' => 'discount_exceeded'
                                    ]
                                ];
                            }
                        }
                    }
                }
            }
        }

        $oldDocumentNumber = $sale->document_number;

        $status = $sale->status;
        if ($isFinishingDraft) {
            $status = ($request->document_type === 'quote' || $request->payment_status === 'pending' || $request->payment_status === 'partial') ? 'pending' : 'completed';
        } else if ($isDraft) {
            $status = 'draft';
        } else if ($sale->status !== 'canceled') {
            if ($request->has('payment_status')) {
                if ($request->payment_status === 'paid') {
                    $status = 'completed';
                } elseif ($request->payment_status === 'pending' || $request->payment_status === 'partial') {
                    $status = 'pending';
                }
            }
        }

        DB::transaction(function () use ($sale, $request, $status, $oldDocumentNumber, $wasQuote, $isNowSale, $wasDraft, $isFinishingDraft) {
            $updateData = $request->only([
                'document_number',
                'client_id',
                'vehicle_id',
                'mileage',
                'service_date',
                'observations',
                'payment_method',
                'document_type',
                'payment_status',
                'is_credited',
                'work_order_id'
            ]);
            if ($request->has('work_order_id')) {
                $wo = WorkOrder::find($request->work_order_id);
                $updateData['work_order_number'] = $wo ? $wo->number : null;
            }
            $sale->update($updateData + ['status' => $status]);

            if ($request->has('technicians')) {
                $technicianIds = WorkOrderSaleSync::resolveTechnicianIds($request, null);
                WorkOrderSaleSync::syncTechniciansToSale($sale, $technicianIds);
            }

            if ($request->has('document_number') && $request->document_number !== $oldDocumentNumber) {
                $financeRecord = FinanceRecord::where('invoice_number', $oldDocumentNumber)->first();
                if ($financeRecord) {
                    $financeRecord->update([
                        'work_order_number' => WorkOrderSaleSync::resolveFinanceWorkOrderNumber(
                            $sale->work_order_id,
                            $request->document_number
                        ),
                        'invoice_number' => $request->document_number,
                        'description' => 'Venta: ' . $sale->document_type . ' - ' . $request->document_number,
                    ]);
                }

                if (method_exists($sale, 'financialMovement')) {
                    $movements = FinancialMovement::where('movable_id', $sale->id)
                        ->where('movable_type', get_class($sale))
                        ->get();

                    foreach ($movements as $movement) {
                        $newDesc = str_replace($oldDocumentNumber, $request->document_number, $movement->description);
                        $metadata = $movement->metadata ?? [];
                        if (isset($metadata['document_number']) && $metadata['document_number'] === $oldDocumentNumber) {
                            $metadata['document_number'] = $request->document_number;
                        }

                        $movement->update([
                            'description' => $newDesc,
                            'metadata' => $metadata
                        ]);
                    }
                }
            }

            // Si se proporcionan items, actualizar el detalle
            if ($request->has('items')) {
                $itemIds = array_filter(array_map('intval', array_column($request->items, 'id')));

                if (!$wasQuote && !$wasDraft) {
                    $itemsToDelete = $sale->details->whereNotIn('id', $itemIds);
                    foreach ($itemsToDelete as $deletedItem) {
                        if ($deletedItem->product_id) {
                            $product = ModelsProduct::find($deletedItem->product_id);
                            if ($product && $product->item_type == 1) {
                                $product->stock += $deletedItem->quantity;
                                $product->save();
                            }
                        }
                    }
                }

                if (!empty($itemIds)) {
                    $sale->details()->whereNotIn('id', $itemIds)->delete();
                } else {
                    $sale->details()->delete();
                }

                foreach ($request->items as $item) {
                    $itemTotal = ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0);

                    if (isset($item['id'])) {
                        $detail = $sale->details->firstWhere('id', $item['id']);

                        if ($detail) {
                            if (!$wasQuote && !$wasDraft) {
                                if ($detail->product_id == ($item['product_id'] ?? null)) {
                                    if ($detail->product_id) {
                                        $product = ModelsProduct::find($detail->product_id);
                                        if ($product && $product->item_type == 1) {
                                            $diff = $item['quantity'] - $detail->quantity;
                                            $product->stock -= $diff;
                                            $product->save();
                                        }
                                    }
                                } else {
                                    if ($detail->product_id) {
                                        $oldProduct = ModelsProduct::find($detail->product_id);
                                        if ($oldProduct && $oldProduct->item_type == 1) {
                                            $oldProduct->stock += $detail->quantity;
                                            $oldProduct->save();
                                        }
                                    }
                                    if (isset($item['product_id']) && $item['product_id']) {
                                        $newProduct = ModelsProduct::find($item['product_id']);
                                        if ($newProduct && $newProduct->item_type == 1) {
                                            $newProduct->stock -= $item['quantity'];
                                            $newProduct->save();
                                        }
                                    }
                                }
                            }

                            $detail->update([
                                'product_id' => $item['product_id'] ?? null,
                                'description' => $item['description'],
                                'quantity' => $item['quantity'],
                                'price' => $item['price'],
                                'discount' => $item['discount'] ?? 0,
                                'total' => $itemTotal,
                            ]);
                        }
                    } else {
                        $sale->details()->create([
                            'product_id' => $item['product_id'] ?? null,
                            'description' => $item['description'],
                            'quantity' => $item['quantity'],
                            'price' => $item['price'],
                            'discount' => $item['discount'] ?? 0,
                            'total' => $itemTotal,
                        ]);

                        if (!$wasQuote && !$wasDraft && isset($item['product_id']) && $item['product_id']) {
                            $product = ModelsProduct::find($item['product_id']);
                            if ($product && $product->item_type == 1) {
                                $product->stock -= $item['quantity'];
                                $product->save();
                            }
                        }
                    }
                }

                $subtotal = $sale->details()->sum('total');
                $taxAmount = $sale->document_type === 'invoice' ? $subtotal * 0.15 : 0;
                $total = $subtotal + $taxAmount;

                $sale->update([
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                ]);

                if (!$wasQuote && !$wasDraft && $sale->document_type !== 'quote' && $sale->status !== 'draft') {
                    $this->financeService->processFinancialRecord($sale, $request->all(), auth()->id() ?? 1);
                }

                if (($wasQuote && $isNowSale) || $isFinishingDraft) {
                    $sale->status = 'completed';
                    $sale->save();

                    foreach ($request->items as $item) {
                        if (isset($item['product_id']) && $item['product_id']) {
                            $product = ModelsProduct::find($item['product_id']);
                            if ($product && $product->item_type == 1) {
                                $product->stock -= $item['quantity'];
                                $product->save();
                            }
                        }
                    }

                    if ($request->document_type !== 'quote') {
                        $this->financeService->processFinancialRecord($sale, $request->all(), auth()->id() ?? 1);

                        if ($sale->work_order_id) {
                            $linkedWorkOrder = WorkOrder::find($sale->work_order_id);
                            if ($linkedWorkOrder) {
                                WorkOrderSaleSync::markAsDelivered($linkedWorkOrder);
                            }
                        }
                    }
                }
            }
        });

        $this->reminderService->syncReplacementReminders($sale);

        // Despachar job SRI si es factura y aún no ha sido encolada/autorizada
        if ($sale->document_type === 'invoice' && empty($sale->sri_status)) {
            try {
                $sale->update(['sri_status' => 'CREADA']);
                if (env('SRI_AUTOPROCESS', true)) {
                    \App\Jobs\SRI\ProcessElectronicInvoice::dispatch($sale->id)->onQueue('sri');
                }
            } catch (Exception $e) {
                \Illuminate\Support\Facades\Log::error("[SRI] Error al despachar factura electrónica en actualización para venta #{$sale->id}: " . $e->getMessage());
            }
        }

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'message' => 'El registro fue actualizado correctamente.',
                'data' => $sale->load('details')
            ]
        ];
    }

    /**
     * Anula una venta completa, revirtiendo stock, finanzas y liberando la orden de trabajo asociada.
     */
    public function deleteSale(Sale $sale): array
    {
        if ($sale->status === 'canceled') {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Esta venta ya se encuentra anulada.'
                ]
            ];
        }

        DB::transaction(function () use ($sale) {
            // 1. Reversar movimientos de cuentas y registros financieros
            if ($sale->financeRecord) {
                $financeRecord = $sale->financeRecord;

                foreach ($financeRecord->paymentDistributions as $distribution) {
                    $account = Account::find($distribution->account_id);
                    if ($account) {
                        $account->updateBalance($distribution->amount, FinanceRecord::TYPE_EXPENSE);
                    }
                }

                $financeRecord->paymentDistributions()->delete();
                $financeRecord->delete();
            }

            // 2. Eliminar movimientos financieros asociados
            if (method_exists($sale, 'financialMovement')) {
                $sale->financialMovement()->delete();
            }

            // 3. Revertir stock si era una venta activa
            if ($sale->document_type !== 'quote' && $sale->status !== 'canceled') {
                foreach ($sale->details as $detail) {
                    if ($detail->product_id) {
                        $product = ModelsProduct::find($detail->product_id);
                        if ($product && $product->item_type == 1) {
                            $product->stock += $detail->quantity;
                            $product->save();
                        }
                    }
                }
            }

            // 4. Liberar la Orden de Trabajo asociada para que pueda volver a ser facturada
            if ($sale->work_order_id) {
                $workOrder = WorkOrder::find($sale->work_order_id);
                if ($workOrder) {
                    // Si estaba como entregada por la facturación, volver a 'ready'
                    if ($workOrder->status === 'delivered') {
                        $workOrder->update(['status' => 'ready']);
                    }
                }
            }

            // 5. Marcar venta como anulada (canceled), desvincularla y ejecutar soft delete para llenar deleted_at
            $sale->update([
                'status' => 'canceled',
                'payment_status' => 'pending',
                'work_order_id' => null,
            ]);

            // Ejecuta soft delete de Eloquent para llenar el campo deleted_at
            $sale->delete();
        });

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'message' => 'El documento ha sido anulado correctamente. Se revirtió el stock, los registros contables y la orden de trabajo quedó disponible para ser facturada nuevamente.'
            ]
        ];
    }

    /**
     * Elimina un ítem específico del detalle de una venta.
     */
    public function deleteSaleDetail(int $detailId): array
    {
        $detail = SaleDetail::find($detailId);

        if (!$detail) {
            return [
                'status' => 404,
                'data' => [
                    'success' => false,
                    'message' => 'El ítem de venta no existe.'
                ]
            ];
        }

        $sale = $detail->sale;
        if (!$sale) {
            return [
                'status' => 404,
                'data' => [
                    'success' => false,
                    'message' => 'Venta asociada no encontrada.'
                ]
            ];
        }

        if ($sale->status === 'canceled') {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'No se puede modificar una venta que ya ha sido anulada.'
                ]
            ];
        }

        if ($sale->document_type === 'invoice' && $sale->sri_status === 'AUTORIZADA') {
            return [
                'status' => 422,
                'data' => [
                    'success' => false,
                    'message' => 'No se pueden eliminar ítems de una factura ya autorizada por el SRI.'
                ]
            ];
        }

        DB::transaction(function () use ($detail, $sale) {
            if ($sale->document_type !== 'quote' && $sale->status !== 'draft') {
                if ($detail->product_id) {
                    $product = ModelsProduct::find($detail->product_id);
                    if ($product && $product->item_type == 1) {
                        $product->stock += $detail->quantity;
                        $product->save();
                    }
                }
            }

            $detail->delete();

            $subtotal = $sale->details()->sum('total');
            $taxAmount = $sale->document_type === 'invoice' ? $subtotal * 0.15 : 0;
            $total = $subtotal + $taxAmount;

            $sale->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);
        });

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'message' => 'El ítem fue eliminado de la base de datos correctamente.',
                'sale' => $sale->fresh()
            ]
        ];
    }
}
