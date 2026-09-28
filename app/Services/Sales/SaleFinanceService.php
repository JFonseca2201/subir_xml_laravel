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

        return (int) env('SRI_AMBIENTE', 1) === 1;
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
                $account = Account::find($distribution->account_id);
                if ($account) {
                    $account->updateBalance($distribution->amount, FinanceRecord::TYPE_EXPENSE); // Restar
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
        $linkedWorkOrder = $sale->work_order_id ? \App\Models\WorkOrder\WorkOrder::with('advances')->find($sale->work_order_id) : null;
        $totalAdvances = $linkedWorkOrder ? (float) $linkedWorkOrder->total_advances : 0.0;
        $balanceToCollectToday = max(0, (float) $sale->total - $totalAdvances);

        $totalPaid = 0;
        if ($sale->payment_status === 'pending') {
            $totalPaid = 0;
        } elseif (!empty($requestData['payment_distributions']) && is_array($requestData['payment_distributions']) && count($requestData['payment_distributions']) > 0) {
            $totalPaid = collect($requestData['payment_distributions'])->sum('amount');
        } else {
            if ($sale->payment_status === 'paid') {
                $totalPaid = $totalAdvances > 0 ? $balanceToCollectToday : (float) $sale->total;
            } else {
                $totalPaid = $sale->financeRecord ? $sale->financeRecord->paymentDistributions->sum('amount') : ($totalAdvances > 0 ? $balanceToCollectToday : (float) $sale->total);
            }
        }

        $primaryAccountId = !empty($requestData['payment_distributions'][0]['account_id'])
            ? $requestData['payment_distributions'][0]['account_id']
            : (Account::where('type', 'cash')->orWhere('name', 'like', '%caja%')->first()?->id ?? Account::first()?->id ?? 1);

        $financeDescription = 'Venta: ' . $sale->document_type . ' - ' . $sale->document_number;
        if ($totalAdvances > 0) {
            $financeDescription .= " (Saldo cobrado hoy tras abono previo de \${$totalAdvances})";
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

        // 4. Procesar pagos distribuidos si existen (solo si no es pendiente)
        if ($sale->payment_status !== 'pending') {
            if (!empty($requestData['payment_distributions']) && is_array($requestData['payment_distributions']) && count($requestData['payment_distributions']) > 0) {
                foreach ($requestData['payment_distributions'] as $distribution) {
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

                    $sale->registerMovement(
                        $distribution['account_id'],
                        'income',
                        $distribution['amount'],
                        'Venta: ' . $sale->document_type . ' - ' . $sale->document_number . ' - ' . $distribution['payment_method'] . ($totalAdvances > 0 ? " (Abono previo de \${$totalAdvances})" : ''),
                        $entryDate,
                        [
                            'document_type' => $sale->document_type,
                            'document_number' => $sale->document_number,
                            'payment_method' => $distribution['payment_method'],
                            'finance_record_id' => $financeRecord->id,
                        ]
                    );
                }
            } else {
                // Si no hay pagos distribuidos, usar el método de pago único y buscar la cuenta dinámicamente
                if (strtolower($paymentMethod) === 'transferencia' || strtolower($paymentMethod) === 'transfer') {
                    $accountId = Account::where('type', 'bank')->first()?->id ?? Account::first()?->id;
                } else {
                    $accountId = Account::where('type', 'cash')->orWhere('name', 'like', '%caja%')->first()?->id ?? Account::first()?->id;
                }

                $amountToAccount = $totalAdvances > 0 ? $balanceToCollectToday : (float) $sale->total;

                PaymentDistribution::create([
                    'finance_record_id' => $financeRecord->id,
                    'account_id' => $accountId,
                    'amount' => $amountToAccount,
                    'payment_method' => $paymentMethod,
                ]);

                $account = Account::find($accountId);
                if ($account) {
                    $account->updateBalance($amountToAccount, FinanceRecord::TYPE_INCOME);
                }

                $sale->registerMovement(
                    $accountId,
                    'income',
                    $amountToAccount,
                    'Venta: ' . $sale->document_type . ' - ' . $sale->document_number . ' - ' . $paymentMethod . ($totalAdvances > 0 ? " (Saldo cobrado tras abono previo de \${$totalAdvances})" : ''),
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

                if (env('SRI_AUTOPROCESS', true)) {
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
