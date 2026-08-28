<?php

namespace App\Services\Sales;

use App\Models\Sales\Sale;
use App\Models\Product\Product as ModelsProduct;
use App\Models\WorkOrder\WorkOrder;
use App\Models\Finance\FinanceRecord;
use App\Models\Finance\PaymentDistribution;
use App\Models\Finance\Account;
use App\Services\SequenceService;
use App\Services\WorkOrder\WorkOrderSaleSync;
use App\Jobs\ProcessElectronicInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class SaleDispatchService
{
    public function __construct(
        protected SaleFinanceService $financeService,
        protected SaleReminderService $reminderService
    ) {}

    /**
     * Despacha una venta con pago pendiente (salida de bodega).
     */
    public function dispatchSale(Request $request): array
    {
        $linkedWorkOrder = null;
        if ($request->work_order_id) {
            $linkedWorkOrder = WorkOrderSaleSync::assertReadyForInvoicing((int) $request->work_order_id);
        }

        // Validar stock antes de procesar el despacho
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

        // Validar descuentos máximos
        foreach ($request->items as $item) {
            if (isset($item['product_id']) && $item['product_id']) {
                $product = ModelsProduct::find($item['product_id']);
                if ($product && $product->item_type == 1 && $product->max_discount !== null) {
                    $maxDiscountAmount = ($item['quantity'] * $item['price']) * ($product->max_discount / 100);
                    if ($item['discount'] > $maxDiscountAmount) {
                        return [
                            'status' => 400,
                            'data' => [
                                'success' => false,
                                'message' => "Descuento excede el máximo permitido para el producto: {$product->description}. Máximo: {$maxDiscountAmount}, Ingresado: {$item['discount']}",
                                'error' => 'discount_exceeded'
                            ]
                        ];
                    }
                }
            }
        }

        $sale = DB::transaction(function () use ($request, $linkedWorkOrder) {
            $documentNumber = SequenceService::consumeNumber('sale_note', $request->document_number);
            
            $sale = Sale::create([
                'document_type' => 'sale_note',
                'document_number' => $documentNumber,
                'client_id' => $request->client_id,
                'vehicle_id' => $request->vehicle_id,
                'work_order_id' => $request->work_order_id,
                'work_order_number' => $linkedWorkOrder ? $linkedWorkOrder->number : ($request->work_order_number ?? ($request->work_order_id ? WorkOrder::find($request->work_order_id)?->number : null)),
                'user_id' => $request->user_id ?? auth()->id() ?? 1,
                'mileage' => $request->mileage,
                'service_date' => $request->service_date ?? now()->format('Y-m-d'),
                'subtotal' => $request->subtotal,
                'tax_amount' => $request->tax_amount,
                'total' => $request->total,
                'status' => 'pending',
                'payment_status' => 'pending',
                'is_credited' => true,
                'payment_method' => 'credit',
                'observations' => $request->observations,
            ]);

            foreach ($request->items as $item) {
                $sale->details()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'discount' => $item['discount'] ?? 0.00,
                    'total' => ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0.00),
                ]);

                if (isset($item['product_id']) && $item['product_id']) {
                    $product = ModelsProduct::find($item['product_id']);
                    if ($product) {
                        $product->stock -= $item['quantity'];
                        $product->save();
                    }
                }
            }

            $technicianIds = WorkOrderSaleSync::resolveTechnicianIds($request, $linkedWorkOrder);
            if (!empty($technicianIds)) {
                WorkOrderSaleSync::syncTechniciansToSale($sale, $technicianIds);
            }

            if ($linkedWorkOrder) {
                WorkOrderSaleSync::markAsDelivered($linkedWorkOrder);
            }

            return $sale;
        });

        return [
            'status' => 201,
            'data' => [
                'success' => true,
                'message' => 'Venta despachada correctamente con pago pendiente.',
                'data' => $sale->load(['details', 'technicians'])
            ]
        ];
    }

    /**
     * Convierte una cotización en venta oficial o factura electrónica.
     */
    public function convertQuote(Sale $quote, Request $request): array
    {
        if ($quote->document_type !== 'quote') {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Solo se pueden convertir cotizaciones.'
                ]
            ];
        }

        if ($quote->is_converted) {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Esta cotización ya fue convertida anteriormente.',
                    'converted_sale_id' => $quote->converted_to_sale_id
                ]
            ];
        }

        if ($quote->status === 'canceled') {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'No se puede convertir una cotización anulada.'
                ]
            ];
        }

        $newSale = null;

        DB::transaction(function () use ($quote, $request, &$newSale) {
            $newDocType = $request->document_type;
            $newDocNumber = SequenceService::consumeNumber($newDocType);

            $total = 0;
            foreach ($quote->details as $detail) {
                $total += ($detail->quantity * $detail->price) - ($detail->discount ?? 0);
            }

            if ($newDocType === 'invoice') {
                $subtotal = round($total / 1.15, 2);
                $taxAmount = round($total - $subtotal, 2);
            } else {
                $subtotal = $total;
                $taxAmount = 0;
            }

            $newSale = Sale::create([
                'document_type' => $newDocType,
                'document_number' => $newDocNumber,
                'client_id' => $quote->client_id,
                'vehicle_id' => $quote->vehicle_id,
                'work_order_id' => $quote->work_order_id,
                'work_order_number' => $quote->workOrder ? $quote->workOrder->number : null,
                'mileage' => $quote->mileage,
                'service_date' => now()->toDateString(),
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'status' => 'completed',
                'payment_status' => $request->payment_status,
                'is_credited' => $request->boolean('is_credited'),
                'payment_method' => $request->payment_method,
                'observations' => $quote->observations,
                'user_id' => $quote->user_id,
            ]);

            foreach ($quote->details as $detail) {
                $newSale->details()->create([
                    'product_id' => $detail->product_id,
                    'description' => $detail->description,
                    'quantity' => $detail->quantity,
                    'price' => $detail->price,
                    'discount' => $detail->discount ?? 0,
                    'total' => ($detail->quantity * $detail->price) - ($detail->discount ?? 0),
                ]);
            }

            if ($quote->technicians->isNotEmpty()) {
                $newSale->technicians()->sync($quote->technicians->pluck('id'));
            }

            if ($request->payment_status !== 'pending' && !$this->financeService->isTestEnvironmentInvoice($newSale)) {
                $financeRecord = FinanceRecord::create([
                    'type' => FinanceRecord::TYPE_INCOME,
                    'amount' => $total,
                    'description' => 'Venta: ' . $newDocType . ' - ' . $newDocNumber,
                    'invoice_number' => $newDocNumber,
                    'user_id' => $quote->user_id,
                    'entry_date' => now()->toDateString(),
                ]);

                if ($request->has('payment_distributions') && is_array($request->payment_distributions)) {
                    foreach ($request->payment_distributions as $distribution) {
                        PaymentDistribution::create([
                            'finance_record_id' => $financeRecord->id,
                            'account_id' => $distribution['account_id'],
                            'amount' => $distribution['amount'],
                            'payment_method' => $distribution['payment_method'],
                        ]);

                        $account = Account::find($distribution['account_id']);
                        if ($account) {
                            $account->updateBalance($distribution['amount'], FinanceRecord::TYPE_INCOME);
                        }

                        $newSale->registerMovement(
                            $distribution['account_id'],
                            'income',
                            $distribution['amount'],
                            'Venta: ' . $newDocType . ' - ' . $newDocNumber . ' - ' . $distribution['payment_method'],
                            now()->toDateString(),
                            [
                                'document_type' => $newDocType,
                                'document_number' => $newDocNumber,
                                'payment_method' => $distribution['payment_method'],
                                'finance_record_id' => $financeRecord->id,
                            ]
                        );
                    }
                } else {
                    $accountId = 1;
                    if (strtolower($request->payment_method) === 'transferencia' || strtolower($request->payment_method) === 'transfer') {
                        $accountId = 2;
                    }

                    PaymentDistribution::create([
                        'finance_record_id' => $financeRecord->id,
                        'account_id' => $accountId,
                        'amount' => $total,
                        'payment_method' => $request->payment_method,
                    ]);

                    $account = Account::find($accountId);
                    if ($account) {
                        $account->updateBalance($total, FinanceRecord::TYPE_INCOME);
                    }

                    $newSale->registerMovement(
                        $accountId,
                        'income',
                        $total,
                        'Venta: ' . $newDocType . ' - ' . $newDocNumber . ' - ' . $request->payment_method,
                        now()->toDateString(),
                        [
                            'document_type' => $newDocType,
                            'document_number' => $newDocNumber,
                            'payment_method' => $request->payment_method,
                            'finance_record_id' => $financeRecord->id,
                        ]
                    );
                }
            }

            foreach ($newSale->details as $detail) {
                if ($detail->product_id) {
                    $product = ModelsProduct::find($detail->product_id);
                    if ($product && $product->stock !== null) {
                        $product->decrement('stock', $detail->quantity);
                    }
                }
            }

            $quote->update(['converted_to_sale_id' => $newSale->id]);

            if ($newDocType === 'invoice') {
                ProcessElectronicInvoice::dispatch($newSale->id)->afterCommit();
            }
        });

        $this->reminderService->syncReplacementReminders($newSale);

        return [
            'status' => 201,
            'data' => [
                'success' => true,
                'message' => 'Cotización convertida exitosamente a ' . ($request->document_type === 'invoice' ? 'factura' : 'nota de venta') . '.',
                'data' => $newSale->load(['details', 'client', 'vehicle'])
            ]
        ];
    }
}
