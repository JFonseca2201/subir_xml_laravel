<?php

namespace App\Services\Sales;

use App\Models\Sales\Sale;
use App\Models\Product\Product as ModelsProduct;
use App\Models\WorkOrder\WorkOrder;
use App\Models\Sales\Quote;
use App\Services\SequenceService;
use App\Services\WorkOrder\WorkOrderSaleSync;
use App\Services\InvoiceStorageService;
use App\Jobs\ProcessElectronicInvoice;
use App\Mail\System\TestNotificationMail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Exception;

class SaleCreationService
{
    public function __construct(
        protected SaleFinanceService $financeService,
        protected SaleReminderService $reminderService
    ) {}

    /**
     * Procesa la creación de una venta, nota de venta o cotización.
     */
    public function createSale(Request $request, int $userId): array
    {
        $linkedWorkOrder = null;
        if ($request->work_order_id) {
            $linkedWorkOrder = WorkOrderSaleSync::assertReadyForInvoicing((int) $request->work_order_id);
        }

        $isDraft = $request->boolean('is_draft');

        // Validar pagos distribuidos solo si no es cotización y no es borrador
        if ($request->document_type !== 'quote' && !$isDraft) {
            if ($request->payment_status === 'pending' || $request->boolean('is_credited')) {
                $request->merge([
                    'payment_status' => 'pending',
                    'is_credited' => true,
                    'payment_method' => $request->payment_method ?: 'Crédito / Pendiente',
                ]);
            } else {
                $hasDistributions = $request->has('payment_distributions') && is_array($request->payment_distributions) && count($request->payment_distributions) > 0;

                if (!$hasDistributions) {
                    return [
                        'status' => 400,
                        'data' => [
                            'success' => false,
                            'message' => 'Debe registrar al menos un pago para la venta.',
                            'error' => 'validation_error'
                        ]
                    ];
                }

                $totalDist = array_sum(array_column($request->payment_distributions, 'amount'));

                if ($totalDist <= 0) {
                    return [
                        'status' => 400,
                        'data' => [
                            'success' => false,
                            'message' => 'Debe registrar al menos un pago para la venta.',
                            'error' => 'validation_error'
                        ]
                    ];
                }

                if ($totalDist > $request->total + 0.01) {
                    return [
                        'status' => 400,
                        'data' => [
                            'success' => false,
                            'message' => 'La suma de los pagos no puede ser mayor al total.',
                            'error' => 'validation_error'
                        ]
                    ];
                }

                if (abs($totalDist - $request->total) <= 0.01) {
                    $paymentStatus = 'paid';
                } elseif ($totalDist > 0) {
                    $paymentStatus = 'partial';
                } else {
                    $paymentStatus = 'pending';
                }

                $request->merge(['payment_status' => $paymentStatus]);
            }
        }

        // 2. Validar stock antes de procesar la venta (solo si no es cotización y es producto físico)
        if ($request->document_type !== 'quote' && !$isDraft) {
            foreach ($request->items as $item) {
                if (isset($item['product_id']) && $item['product_id']) {
                    $product = ModelsProduct::find($item['product_id']);
                    if ($product && $product->item_type == 1 && $product->stock < $item['quantity']) {
                        return [
                            'status' => 400,
                            'data' => [
                                'success' => false,
                                'message' => "Stock insuficiente para el producto: {$product->description}. Stock disponible: {$product->stock}, Solicitado: {$item['quantity']}",
                                'error' => 'stock_insufficient'
                            ]
                        ];
                    }
                }
            }
        }

        // 3. Validar descuentos y margen (sólo aplica para item_type == 1 / Productos Físicos)
        foreach ($request->items as $item) {
            if (isset($item['product_id']) && $item['product_id']) {
                $product = ModelsProduct::find($item['product_id']);
                if ($product && $product->item_type == 1) {
                    // A. Validar que el precio final no sea menor al precio de compra (purchase_price)
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

                    // B. Validar porcentaje de descuento máximo definido
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

                    // C. Validar max_discount
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

        $paymentMethod = $this->financeService->resolveSalePaymentMethod($request->all(), $request->payment_method ?: 'Efectivo');

        // 4. Iniciamos la transacción para asegurar consistencia atómica
        $sale = DB::transaction(function () use ($request, $linkedWorkOrder, $paymentMethod, $isDraft, $userId) {
            if ($request->document_type === 'quote') {
                $documentNumber = SequenceService::consumeQuoteNumber($request->document_number);
            } else {
                $documentNumber = SequenceService::consumeNumber($request->document_type, $request->document_number);
            }

            // A. Crear la cabecera de la venta
            $sale = Sale::create([
                'document_type' => $request->document_type,
                'document_number' => $documentNumber,
                'client_id' => $request->client_id,
                'vehicle_id' => $request->vehicle_id,
                'work_order_id' => $request->work_order_id,
                'work_order_number' => $linkedWorkOrder ? $linkedWorkOrder->number : ($request->work_order_number ?? ($request->work_order_id ? WorkOrder::find($request->work_order_id)?->number : null)),
                'user_id' => $userId,
                'mileage' => $request->mileage,
                'service_date' => $request->service_date ?? now()->format('Y-m-d'),
                'subtotal' => $request->subtotal,
                'tax_amount' => $request->tax_amount,
                'total' => $request->total,
                'status' => $isDraft ? 'draft' : ($request->document_type === 'quote' || $request->payment_status === 'pending' || $request->payment_status === 'partial' ? 'pending' : 'completed'),
                'payment_status' => $request->payment_status,
                'is_credited' => $request->is_credited,
                'payment_method' => $paymentMethod,
                'observations' => $request->observations,
            ]);

            // B. Registrar cada producto/servicio del detalle
            foreach ($request->items as $item) {
                $qty      = (float)($item['quantity'] ?? 1);
                $price    = (float)($item['price'] ?? 0);
                $discount = (float)($item['discount'] ?? 0);
                $taxRate  = (float)($item['tax_rate'] ?? 15.00);
                $base     = ($qty * $price) - $discount;
                $taxValue = round($base * ($taxRate / 100), 2);

                $sale->details()->create([
                    'product_id'  => $item['product_id'] ?? null,
                    'description' => $item['description'],
                    'quantity'    => $qty,
                    'price'       => $price,
                    'discount'    => $discount,
                    'tax_rate'    => $taxRate,
                    'tax_value'   => $taxValue,
                    'total'       => $base,
                ]);

                // Deducir stock de forma atómica solo si no es cotización y es producto físico
                if ($request->document_type !== 'quote' && !$isDraft && isset($item['product_id']) && $item['product_id']) {
                    $product = ModelsProduct::find($item['product_id']);
                    if ($product && $product->item_type == 1) {
                        ModelsProduct::where('id', $item['product_id'])->decrement('stock', $item['quantity']);
                    }
                }
            }

            // 💰 Actualizar cuentas financieras si no es cotización ni borrador
            if ($request->document_type !== 'quote' && !$isDraft) {
                $reqData = $request->all();
                $reqData['payment_method'] = $paymentMethod;
                $this->financeService->processFinancialRecord($sale, $reqData, $userId);
            }

            $technicianIds = WorkOrderSaleSync::resolveTechnicianIds($request, $linkedWorkOrder);
            if (!empty($technicianIds)) {
                WorkOrderSaleSync::syncTechniciansToSale($sale, $technicianIds);
            }

            if ($linkedWorkOrder && !$isDraft) {
                WorkOrderSaleSync::markAsDelivered($linkedWorkOrder);
            }

            if ($request->document_type === 'invoice' && !$isDraft) {
                $sale->update(['sri_status' => 'CREADA']);
            }

            if ($request->has('quote_id') && $request->quote_id) {
                Quote::where('id', $request->quote_id)->update([
                    'converted_sale_id' => $sale->id,
                    'status' => 'completed',
                ]);
            }

            return $sale;
        });

        // ── Despachar job de Facturación Electrónica SRI ─────────────
        if ($sale->document_type === 'invoice' && $sale->status !== 'draft') {
            try {
                ProcessElectronicInvoice::dispatch($sale->id);
                Log::info("[SRI] Job despachado para venta #{$sale->id}");
            } catch (\Throwable $e) {
                Log::error("[SRI] Error al despachar factura electrónica para venta #{$sale->id}: " . $e->getMessage());
            }
        }

        // 📬 Envío automático de email con comprobante PDF
        if ($sale->document_type !== 'quote' && $sale->status !== 'draft') {
            try {
                $sale->load(['client', 'vehicle', 'user', 'details.product', 'technicians', 'financeRecord.paymentDistributions.account']);

                $vehicleBrands = config('vehicle_brands', []);
                if ($sale->vehicle && isset($sale->vehicle->brand)) {
                    $brandId = $sale->vehicle->brand;
                    $sale->vehicle->brand = $vehicleBrands[$brandId] ?? $brandId;
                }

                $pdf = Pdf::loadView('sales.pdf_sale', ['sale' => $sale, 'isEmail' => true]);
                $pdfRawData = $pdf->output();
                $pdfFileName = $sale->document_type . '_' . $sale->document_number . '.pdf';

                $data = [
                    'titulo_asunto' => ($sale->document_type === 'invoice' ? 'Factura' : 'Nota de Venta') . ' #' . $sale->document_number,
                    'cliente' => $sale->client->full_name ?? 'Cliente',
                    'mensaje_principal' => 'Tu transacción ha sido procesada con éxito. Agradecemos tu confianza en Luxury Evys. Adjunto a este correo encontrarás el comprobante oficial en formato PDF con el detalle de los servicios prestados.',
                    'vehiculo' => $sale->vehicle ? ($sale->vehicle->brand . ' ' . $sale->vehicle->model) : 'N/A',
                    'placa' => $sale->vehicle->license_plate ?? 'N/A',
                    'accion' => 'Comprobante de Servicio Generado',
                    'encuesta_url' => 'https://docs.google.com/forms/d/1pcVsHD2XcGbghjW4j7XgDVihb5-oB7otvnvMbd4sBY0/viewform'
                ];

                if (!empty($sale->client->email)) {
                    Mail::to($sale->client->email)->send(
                        new TestNotificationMail($data, $pdfRawData, $pdfFileName)
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Error al enviar email automático de venta:', ['error' => $e->getMessage()]);
            }
        }

        $this->reminderService->syncReplacementReminders($sale);

        // Guardar comprobantes adjuntos si fueron enviados en el request
        if ($request->hasFile('receipts')) {
            try {
                $storageService = app(InvoiceStorageService::class);
                $clientName = $sale->client->full_name ?? ($sale->client ? trim(($sale->client->name ?? '') . ' ' . ($sale->client->surname ?? '')) : 'Cliente');
                $storageService->attachReceiptsToModel(
                    $sale,
                    $sale->document_number ?: $sale->id,
                    $clientName,
                    $request->file('receipts'),
                    $sale->service_date ?? now()
                );
            } catch (Exception $e) {
                Log::warning('Error al adjuntar comprobantes a Sale:', ['error' => $e->getMessage()]);
            }
        }

        return [
            'status' => 201,
            'data' => [
                'success' => true,
                'message' => 'El registro se procesó correctamente.',
                'data' => $sale->load(['details', 'technicians', 'attachments'])
            ]
        ];
    }
}
