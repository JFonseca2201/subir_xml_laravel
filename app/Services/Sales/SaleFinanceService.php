<?php

namespace App\Services\Sales;

use App\Models\Sales\Sale;
use App\Models\Finance\FinanceRecord;
use App\Models\Finance\PaymentDistribution;
use App\Models\Finance\Account;
use App\Models\Config\Sucursale;
use App\Services\WorkOrder\WorkOrderSaleSync;
use App\Services\SequenceService;
use App\Jobs\ProcessElectronicInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SaleFinanceService
{
    /**
     * Verifica si una factura corresponde a un entorno de pruebas del SRI.
     */
    public function isTestEnvironmentInvoice(Sale $sale): bool
    {
        if ($sale->document_type !== 'invoice') {
            return false;
        }

        $sucursalId = $sale->client->sucursale_id ?? optional($sale->user)->sucursale_id ?? 1;
        $sucursal = Sucursale::find($sucursalId) ?? Sucursale::first();

        if ($sucursal && !empty($sucursal->ambiente)) {
            return (int) $sucursal->ambiente === 1; // 1: Pruebas, 2: Producción
        }

        return (int) config('sri.ambiente', 1) === 1;
    }

    /**
     * Procesa el registro financiero de la venta y actualiza los saldos de cuentas.
     */
    public function processFinancialRecord(Sale $sale, array $requestData = [], int $userId = 1): void
    {

        // 1. Buscar si ya existe un registro financiero para esta venta
        $financeRecord = FinanceRecord::where('invoice_number', $sale->document_number)->first();

        // 2. Si ya existe, revertir los saldos de las distribuciones anteriores
        if ($financeRecord) {
            foreach ($financeRecord->paymentDistributions as $distribution) {
                if (!$distribution->is_advance) {
                    $account = Account::find($distribution->account_id);
                    if ($account) {
                        $account->updateBalance($distribution->amount, FinanceRecord::TYPE_EXPENSE); // Restar
                    }
                }
            }
            // Eliminar las distribuciones anteriores
            $financeRecord->paymentDistributions()->delete();
        } else {
            $financeRecord = new FinanceRecord();
        }

        // Eliminar TODOS los movimientos financieros anteriores vinculados a esta venta
        \App\Models\Finance\FinancialMovement::where('movable_type', get_class($sale))
            ->where('movable_id', $sale->id)
            ->delete();

        if ($financeRecord->id) {
            \App\Models\Finance\FinancialMovement::where('metadata->finance_record_id', $financeRecord->id)->delete();
        }

        \App\Models\Finance\FinancialMovement::where(function ($q) use ($sale) {
            $q->where('metadata->invoice', $sale->document_number)
                ->orWhere('metadata->document_number', $sale->document_number)
                ->orWhere('description', 'like', "%{$sale->document_number}%");
        })->where('movable_type', get_class($sale))->delete();

        $entryDate = $sale->service_date instanceof \Carbon\Carbon
            ? $sale->service_date->format('Y-m-d')
            : (is_string($sale->service_date) ? substr($sale->service_date, 0, 10) : now()->format('Y-m-d'));

        $paymentMethod = $requestData['payment_method'] ?? $sale->payment_method ?? 'Efectivo';

        // 🔍 Verificar si la orden de trabajo vinculada ya tenía anticipos/abonos previos
        $linkedWorkOrder = $sale->work_order_id ? \App\Models\WorkOrder\WorkOrder::with(['advances.account'])->find($sale->work_order_id) : null;
        $workOrderAdvances = $linkedWorkOrder ? $linkedWorkOrder->advances()->whereNull('deleted_at')->get() : collect();
        $totalAdvances = (float) $workOrderAdvances->sum('amount');
        $advanceIds = $workOrderAdvances->pluck('id')->all();
        $balanceToCollectToday = max(0, (float) $sale->total - $totalAdvances);

        $incomingDists = $requestData['payment_distributions'] ?? [];
        if (!is_array($incomingDists)) {
            $incomingDists = [];
        }

        // Si la OT tiene anticipos previos, asegurar que el desglose de pagos refleje el anticipo de forma dividida
        if ($totalAdvances > 0) {
            $hasAdvanceDist = collect($incomingDists)->contains(fn ($d) => !empty($d['is_advance']));

            if (!$hasAdvanceDist) {
                $advanceDists = [];
                foreach ($workOrderAdvances as $adv) {
                    $advanceDists[] = [
                        'account_id' => $adv->account_id,
                        'amount' => (float) $adv->amount,
                        'payment_method' => $adv->payment_method ?: 'Efectivo',
                        'is_advance' => true,
                        'work_order_advance_id' => $adv->id,
                    ];
                }

                $incomingSum = array_sum(array_column($incomingDists, 'amount'));

                // Si se envió solo el saldo a cobrar hoy (ej. $100) o vino vacío:
                if (abs($incomingSum - $balanceToCollectToday) <= 0.05 || count($incomingDists) === 0) {
                    $incomingDists = array_merge($advanceDists, $incomingDists);
                } elseif (abs($incomingSum - (float) $sale->total) <= 0.05) {
                    // Si se envió el total ($120) completo sin separar el anticipo, separar para no duplicar
                    $adjustedRegular = [];
                    $remAdvance = $totalAdvances;
                    foreach ($incomingDists as $d) {
                        $amt = (float) $d['amount'];
                        if ($remAdvance > 0) {
                            if ($amt <= $remAdvance) {
                                $remAdvance -= $amt;
                            } else {
                                $d['amount'] = round($amt - $remAdvance, 2);
                                $d['is_advance'] = false;
                                $remAdvance = 0;
                                $adjustedRegular[] = $d;
                            }
                        } else {
                            $d['is_advance'] = false;
                            $adjustedRegular[] = $d;
                        }
                    }
                    $incomingDists = array_merge($advanceDists, $adjustedRegular);
                } else {
                    $incomingDists = array_merge($advanceDists, $incomingDists);
                }
            }
        }

        $totalPaid = !empty($incomingDists) ? collect($incomingDists)->sum('amount') : (float) $sale->total;

        $primaryAccountId = !empty($incomingDists[0]['account_id'])
            ? $incomingDists[0]['account_id']
            : (Account::where('type', 'cash')->orWhere('name', 'like', '%caja%')->first()?->id ?? Account::first()?->id ?? 1);

        $docTypeLabel = match (strtolower($sale->document_type ?? '')) {
            'invoice' => 'Factura',
            'note' => 'Nota de Venta',
            'quote' => 'Cotización',
            default => ucfirst($sale->document_type ?? 'Factura'),
        };

        $financeDescription = 'Venta: ' . $docTypeLabel . ' - ' . $sale->document_number;
        if ($totalAdvances > 0) {
            $financeDescription .= " (Incluye anticipo de \${$totalAdvances} y saldo de \${$balanceToCollectToday})";
        }

        // 3. Crear/Actualizar el registro financiero principal
        $financeRecord->fill([
            'entry_date' => $entryDate,
            'type' => FinanceRecord::TYPE_INCOME,
            'account_id' => $primaryAccountId,
            'payment_method' => $paymentMethod,
            'amount' => $totalPaid,
            'work_order_number' => WorkOrderSaleSync::resolveFinanceWorkOrderNumber(
                $sale->work_order_id,
                $sale->document_number
            ),
            'invoice_number' => $sale->document_number,
            'description' => $financeDescription,
            'user_id' => $sale->user_id ?? $userId,
        ]);
        $financeRecord->save();

        // 4. Procesar pagos distribuidos de forma dividida (Anticipo vs Saldo)
        if (!empty($incomingDists)) {
            foreach ($incomingDists as $distribution) {
                $isAdvance = !empty($distribution['is_advance'])
                    || (isset($distribution['work_order_advance_id']) && in_array($distribution['work_order_advance_id'], $advanceIds));

                $advId = $distribution['work_order_advance_id'] ?? null;
                if (!$advId && $isAdvance && $workOrderAdvances->isNotEmpty()) {
                    $matchAdv = $workOrderAdvances->firstWhere('account_id', $distribution['account_id']) ?? $workOrderAdvances->first();
                    $advId = $matchAdv?->id;
                }

                PaymentDistribution::create([
                    'finance_record_id' => $financeRecord->id,
                    'account_id' => $distribution['account_id'],
                    'amount' => $distribution['amount'],
                    'payment_method' => $distribution['payment_method'],
                    'is_advance' => $isAdvance,
                    'work_order_advance_id' => $advId,
                ]);

                if ($isAdvance) {
                    // ⚠️ El dinero ya ingresó físicamente y a bancos cuando se registró el anticipo en la OT.
                    // NO se incrementa la cuenta contable para evitar duplicidad de saldos.
                    // NO se genera un nuevo movimiento de ingreso en financial_movements.
                    // Se vincula el movimiento del anticipo existente con la factura para trazabilidad.
                    \App\Models\Finance\FinancialMovement::where('movable_type', \App\Models\WorkOrder\WorkOrder::class)
                        ->where('movable_id', $sale->work_order_id)
                        ->where(function ($q) use ($distribution, $advId) {
                            if ($advId) {
                                $q->where('metadata->work_order_advance_id', $advId);
                            }
                            $q->orWhere('amount', $distribution['amount']);
                        })
                        ->update([
                            'metadata->invoice_id' => $sale->id,
                            'metadata->invoice_number' => $sale->document_number,
                            'metadata->applied_to_invoice' => true,
                        ]);
                } else {
                    // ✅ Saldo real ingresado al facturar
                    $account = Account::find($distribution['account_id']);
                    if ($account) {
                        $account->updateBalance($distribution['amount'], FinanceRecord::TYPE_INCOME);
                    }

                    $sale->registerMovement(
                        $distribution['account_id'],
                        'income',
                        $distribution['amount'],
                        'Venta: ' . $docTypeLabel . ' - ' . $sale->document_number . ' - ' . $distribution['payment_method'] . ($totalAdvances > 0 ? " (Saldo tras anticipo previo de \${$totalAdvances})" : ''),
                        $entryDate,
                        [
                            'document_type' => $sale->document_type,
                            'document_number' => $sale->document_number,
                            'payment_method' => $distribution['payment_method'],
                            'finance_record_id' => $financeRecord->id,
                            'work_order_id' => $sale->work_order_id,
                        ]
                    );
                }
            }
        } elseif ($sale->payment_status !== 'pending') {
            // Fallback si no hay distribuciones (método único)
            if (strtolower($paymentMethod) === 'transferencia' || strtolower($paymentMethod) === 'transfer') {
                $accountId = Account::where('type', 'bank')->first()?->id ?? Account::first()?->id;
            } else {
                $accountId = Account::where('type', 'cash')->orWhere('name', 'like', '%caja%')->first()?->id ?? Account::first()?->id;
            }

            if ($totalAdvances > 0) {
                // Registrar anticipo previo
                $advAccount = $workOrderAdvances->first()?->account_id ?? $accountId;
                PaymentDistribution::create([
                    'finance_record_id' => $financeRecord->id,
                    'account_id' => $advAccount,
                    'amount' => $totalAdvances,
                    'payment_method' => $workOrderAdvances->first()?->payment_method ?? 'Efectivo',
                    'is_advance' => true,
                    'work_order_advance_id' => $workOrderAdvances->first()?->id,
                ]);

                // Registrar saldo restante
                PaymentDistribution::create([
                    'finance_record_id' => $financeRecord->id,
                    'account_id' => $accountId,
                    'amount' => $balanceToCollectToday,
                    'payment_method' => $paymentMethod,
                    'is_advance' => false,
                ]);

                $account = Account::find($accountId);
                if ($account) {
                    $account->updateBalance($balanceToCollectToday, FinanceRecord::TYPE_INCOME);
                }

                $sale->registerMovement(
                    $accountId,
                    'income',
                    $balanceToCollectToday,
                    'Venta: ' . $docTypeLabel . ' - ' . $sale->document_number . ' - ' . $paymentMethod . " (Saldo tras anticipo previo de \${$totalAdvances})",
                    $entryDate,
                    [
                        'document_type' => $sale->document_type,
                        'document_number' => $sale->document_number,
                        'payment_method' => $paymentMethod,
                        'finance_record_id' => $financeRecord->id,
                        'work_order_id' => $sale->work_order_id,
                    ]
                );
            } else {
                PaymentDistribution::create([
                    'finance_record_id' => $financeRecord->id,
                    'account_id' => $accountId,
                    'amount' => (float) $sale->total,
                    'payment_method' => $paymentMethod,
                    'is_advance' => false,
                ]);

                $account = Account::find($accountId);
                if ($account) {
                    $account->updateBalance((float) $sale->total, FinanceRecord::TYPE_INCOME);
                }

                $sale->registerMovement(
                    $accountId,
                    'income',
                    (float) $sale->total,
                    'Venta: ' . $docTypeLabel . ' - ' . $sale->document_number . ' - ' . $paymentMethod,
                    $entryDate,
                    [
                        'document_type' => $sale->document_type,
                        'document_number' => $sale->document_number,
                        'payment_method' => $paymentMethod,
                        'finance_record_id' => $financeRecord->id,
                    ]
                );
            }
        }
    }

    /**
     * Obtiene el método de pago real desde los pagos distribuidos (si existen).
     */
    public function resolveSalePaymentMethod(array $requestData, string $defaultMethod = 'Efectivo'): string
    {
        if (
            !empty($requestData['payment_distributions'])
            && is_array($requestData['payment_distributions'])
            && count($requestData['payment_distributions']) > 0
        ) {
            $methods = collect($requestData['payment_distributions'])
                ->pluck('payment_method')
                ->filter()
                ->unique()
                ->values();

            if ($methods->count() === 1) {
                return (string) $methods->first();
            }

            if ($methods->count() > 1) {
                return $methods->implode(', ');
            }
        }

        return (string) ($requestData['payment_method'] ?? $defaultMethod);
    }

    /**
     * Registra el pago para una venta en estado pendiente.
     */
    public function registerPayment(Sale $sale, array $requestData, int $userId): array
    {
        if ($sale->payment_status === 'paid') {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Esta venta ya está pagada.'
                ]
            ];
        }

        if ($sale->payment_status !== 'pending') {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Solo se puede registrar pago para ventas con estado pendiente.'
                ]
            ];
        }

        DB::transaction(function () use ($sale, $requestData, $userId) {
            $sale->update([
                'payment_status' => 'paid',
                'payment_method' => $requestData['payment_method'],
                'status' => 'completed',
            ]);

            // Convertir a factura si se solicita
            if (!empty($requestData['convert_to_invoice']) && $sale->document_type !== 'invoice') {
                $newDocNumber = SequenceService::getNextInvoiceNumber();
                $sale->update([
                    'document_type' => 'invoice',
                    'document_number' => $newDocNumber,
                    'sri_status' => 'CREADA',
                ]);

                if (config('sri.autoprocess', true)) {
                    ProcessElectronicInvoice::dispatch($sale->id)->onQueue('sri');
                }
            }
            /* aquí se debe llamar la funcion proccess financial record */
            $this->processFinancialRecord($sale, $requestData, $userId);
        });

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'message' => 'Pago registrado exitosamente.',
                'sale' => $sale->fresh(['client', 'vehicle', 'details', 'financeRecord.paymentDistributions'])
            ]
        ];
    }
}
