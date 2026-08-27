<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\AccountPayable;
use App\Models\Finance\FinanceRecord;
use App\Models\Finance\PaymentDistribution;
use App\Models\Invoice\Invoice;
use App\Models\Supplier\Supplier;
use App\Models\Supplier\SupplierCreditBalance;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierReconciliationController extends Controller
{
    /**
     * Obtiene las facturas pendientes de pago de un proveedor.
     */
    public function getPendingInvoices(int $supplierId): JsonResponse
    {
        try {
            $supplier = Supplier::findOrFail($supplierId);

            // 1. Obtener todas las facturas del proveedor ordenadas por fecha más reciente
            $invoices = Invoice::where('supplier_id', $supplierId)
                ->with(['accountPayable'])
                ->orderBy('issue_date', 'desc')
                ->get();

            $invoicesList = [];

            foreach ($invoices as $inv) {
                $totalAmount = (float) ($inv->total ?? $inv->total_amount ?? 0.0);
                $paidAmount = $inv->accountPayable ? (float) $inv->accountPayable->amount_paid : 0.0;
                $balanceDue = $inv->accountPayable ? max(0.0, round($totalAmount - $paidAmount, 2)) : $totalAmount;

                $invoicesList[] = [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'issue_date' => $inv->issue_date ? (is_string($inv->issue_date) ? substr($inv->issue_date, 0, 10) : $inv->issue_date->format('Y-m-d')) : null,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'balance_due' => $balanceDue > 0 ? $balanceDue : $totalAmount,
                    'status' => $inv->accountPayable ? $inv->accountPayable->status : 'emitida',
                    'description' => $inv->description ?? 'Compra / Factura',
                ];
            }

            return response()->json([
                'success' => true,
                'supplier' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'ruc' => $supplier->ruc,
                ],
                'data' => $invoicesList,
            ], 200);
        } catch (Exception $e) {
            Log::error('Error al obtener facturas pendientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener facturas pendientes del proveedor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene los saldos a favor y notas de crédito disponibles de un proveedor.
     */
    public function getAvailableCredits(int $supplierId): JsonResponse
    {
        try {
            $supplier = Supplier::findOrFail($supplierId);

            $credits = SupplierCreditBalance::where('supplier_id', $supplierId)
                ->whereIn('status', ['available', 'partially_used'])
                ->where('remaining_balance', '>', 0.001)
                ->orderBy('created_at', 'desc')
                ->get();

            $totalAvailable = (float) $credits->sum('remaining_balance');

            return response()->json([
                'success' => true,
                'supplier_id' => $supplierId,
                'total_available' => $totalAvailable,
                'data' => $credits,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener saldos a favor del proveedor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesa la conciliación y pago de facturas con cálculo de excedente/saldo a favor.
     */
    public function reconcilePayment(Request $request): JsonResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'account_id' => 'required|exists:accounts,id',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'reference_number' => 'nullable|string',
            'actual_payment_amount' => 'required|numeric|min:0.01',
            'invoices' => 'required|array|min:1',
            'invoices.*.id' => 'required|exists:invoices,id',
            'invoices.*.amount_to_pay' => 'required|numeric|min:0.01',
            'difference_resolution' => 'nullable|in:credit_balance,credit_note,immediate_refund',
            'credit_note_number' => 'nullable|string',
            'refund_account_id' => 'nullable|exists:accounts,id',
            'notes' => 'nullable|string',
        ]);

        try {
            $result = DB::transaction(function () use ($request) {
                $supplier = Supplier::findOrFail($request->supplier_id);
                $account = Account::findOrFail($request->account_id);

                $actualPayment = (float) $request->actual_payment_amount;
                $invoicesData = $request->invoices;
                $invoicesTotal = (float) collect($invoicesData)->sum('amount_to_pay');
                $difference = round($actualPayment - $invoicesTotal, 2);

                $paymentDate = $request->payment_date;
                $paymentMethod = $request->payment_method;
                $reference = $request->reference_number ?: 'PAGO-PROV-' . date('Ymd-His');

                // 1. Registrar el egreso contable/bancario total real en la cuenta de origen
                $account->updateBalance($actualPayment, FinanceRecord::TYPE_EXPENSE);

                // 2. Crear el registro financiero principal del egreso
                $financeRecord = FinanceRecord::create([
                    'type' => FinanceRecord::TYPE_EXPENSE,
                    'amount' => $actualPayment,
                    'description' => "Pago conciliado a proveedor: {$supplier->name} (Ref: {$reference})",
                    'invoice_number' => $reference,
                    'user_id' => auth()->id() ?? 1,
                    'entry_date' => $paymentDate,
                ]);

                // 3. Procesar cada factura seleccionada y liquidar saldos
                $appliedInvoices = [];
                foreach ($invoicesData as $item) {
                    $invoice = Invoice::with('accountPayable')->findOrFail($item['id']);
                    $amountToPay = (float) $item['amount_to_pay'];

                    // A. Crear distribución de pago asociada a la factura
                    PaymentDistribution::create([
                        'finance_record_id' => $financeRecord->id,
                        'account_id' => $account->id,
                        'amount' => $amountToPay,
                        'payment_method' => $paymentMethod,
                        'metadata' => [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'supplier_id' => $supplier->id,
                            'reconciliation_ref' => $reference,
                        ],
                    ]);

                    // B. Actualizar AccountPayable si existe
                    if ($invoice->accountPayable) {
                        $ap = $invoice->accountPayable;
                        $newPaid = round((float) $ap->amount_paid + $amountToPay, 2);
                        $apTotal = (float) $ap->total_amount;
                        $newStatus = ($newPaid >= $apTotal - 0.01) ? 'paid' : 'partial';

                        $ap->update([
                            'amount_paid' => $newPaid,
                            'status' => $newStatus,
                        ]);
                    } else {
                        // Crear AccountPayable si no existía para mantener tracking
                        $invTotal = (float) ($invoice->total ?? $invoice->total_amount ?? $amountToPay);
                        AccountPayable::create([
                            'supplier_id' => $supplier->id,
                            'invoice_id' => $invoice->id,
                            'total_amount' => $invTotal,
                            'amount_paid' => $amountToPay,
                            'status' => ($amountToPay >= $invTotal - 0.01) ? 'paid' : 'partial',
                        ]);
                    }

                    // C. Marcar proceso de factura
                    $invoice->update([
                        'invoice_process' => 1,
                    ]);

                    $appliedInvoices[] = [
                        'invoice_number' => $invoice->invoice_number,
                        'amount_applied' => $amountToPay,
                    ];
                }

                // 4. Resolver el sobrante / saldo a favor si existe diferencia
                $creditBalanceRecord = null;
                $refundRecord = null;

                if ($difference > 0.001) {
                    $resolution = $request->difference_resolution ?: 'credit_balance';

                    if ($resolution === 'credit_note') {
                        // Opción B: Registrar Nota de Crédito
                        $creditBalanceRecord = SupplierCreditBalance::create([
                            'supplier_id' => $supplier->id,
                            'account_id' => $account->id,
                            'finance_record_id' => $financeRecord->id,
                            'source_type' => 'credit_note',
                            'reference_number' => $request->credit_note_number ?: ('NC-' . $reference),
                            'total_payment_amount' => $actualPayment,
                            'invoices_total_amount' => $invoicesTotal,
                            'amount' => $difference,
                            'used_amount' => 0.00,
                            'remaining_balance' => $difference,
                            'status' => 'available',
                            'resolution_type' => 'credit_note',
                            'notes' => $request->notes ?: "Nota de crédito por diferencia en pago a proveedor ({$reference})",
                        ]);
                    } elseif ($resolution === 'immediate_refund') {
                        // Opción C: Devolución inmediata a cuenta bancaria
                        $refundAccountId = $request->refund_account_id ?: $account->id;
                        $refundAccount = Account::findOrFail($refundAccountId);

                        // Acreditar el dinero reembolsado en la cuenta destino
                        $refundAccount->updateBalance($difference, FinanceRecord::TYPE_INCOME);

                        // Registrar ingreso financiero por devolución
                        $refundRecord = FinanceRecord::create([
                            'type' => FinanceRecord::TYPE_INCOME,
                            'amount' => $difference,
                            'description' => "Reembolso/Devolución de excedente de pago: {$supplier->name} (Ref: {$reference})",
                            'invoice_number' => 'DEV-' . $reference,
                            'user_id' => auth()->id() ?? 1,
                            'entry_date' => $paymentDate,
                        ]);

                        PaymentDistribution::create([
                            'finance_record_id' => $refundRecord->id,
                            'account_id' => $refundAccount->id,
                            'amount' => $difference,
                            'payment_method' => 'Transferencia / Devolución',
                            'metadata' => [
                                'supplier_id' => $supplier->id,
                                'reconciliation_ref' => $reference,
                            ],
                        ]);

                        // Registrar saldo con estado 'refunded' para trazabilidad
                        $creditBalanceRecord = SupplierCreditBalance::create([
                            'supplier_id' => $supplier->id,
                            'account_id' => $refundAccount->id,
                            'finance_record_id' => $refundRecord->id,
                            'source_type' => 'overpayment',
                            'reference_number' => 'REEMBOLSO-' . $reference,
                            'total_payment_amount' => $actualPayment,
                            'invoices_total_amount' => $invoicesTotal,
                            'amount' => $difference,
                            'used_amount' => $difference,
                            'remaining_balance' => 0.00,
                            'status' => 'refunded',
                            'resolution_type' => 'immediate_refund',
                            'notes' => "Diferencia de \${$difference} reembolsada a cuenta {$refundAccount->name}",
                        ]);
                    } else {
                        // Opción A: Guardar como Saldo a Favor / Anticipo
                        $creditBalanceRecord = SupplierCreditBalance::create([
                            'supplier_id' => $supplier->id,
                            'account_id' => $account->id,
                            'finance_record_id' => $financeRecord->id,
                            'source_type' => 'overpayment',
                            'reference_number' => $reference,
                            'total_payment_amount' => $actualPayment,
                            'invoices_total_amount' => $invoicesTotal,
                            'amount' => $difference,
                            'used_amount' => 0.00,
                            'remaining_balance' => $difference,
                            'status' => 'available',
                            'resolution_type' => 'credit_balance',
                            'notes' => $request->notes ?: "Saldo a favor generado por excedente de pago ({$reference})",
                        ]);
                    }
                }

                return [
                    'finance_record_id' => $financeRecord->id,
                    'supplier_name' => $supplier->name,
                    'actual_payment' => $actualPayment,
                    'invoices_total' => $invoicesTotal,
                    'difference' => $difference,
                    'credit_balance' => $creditBalanceRecord,
                    'applied_invoices' => $appliedInvoices,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Conciliación de pago procesada exitosamente.',
                'data' => $result,
            ], 200);
        } catch (Exception $e) {
            Log::error('Error en reconcilePayment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la conciliación de pago.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listado con filtros y paginación de saldos a favor / notas de crédito.
     */
    public function indexCredits(Request $request): JsonResponse
    {
        try {
            $query = SupplierCreditBalance::with(['supplier', 'account', 'usages.invoice']);

            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('source_type')) {
                $query->where('source_type', $request->source_type);
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('created_at', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
            }

            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('reference_number', 'like', "%{$search}%")
                      ->orWhereHas('supplier', function ($sq) use ($search) {
                          $sq->where('name', 'like', "%{$search}%")
                             ->orWhere('ruc', 'like', "%{$search}%");
                      });
                });
            }

            $credits = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $credits,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener saldos a favor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesa la devolución/reembolso a cuenta de un saldo a favor disponible.
     */
    public function refundCredit(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);

        try {
            $result = DB::transaction(function () use ($request, $id) {
                $credit = SupplierCreditBalance::with('supplier')->findOrFail($id);
                $refundAmount = (float) $request->amount;

                if ($refundAmount > (float) $credit->remaining_balance + 0.001) {
                    throw new Exception('El monto a reembolsar excede el saldo disponible.');
                }

                $account = Account::findOrFail($request->account_id);

                // 1. Acreditar el dinero en la cuenta
                $account->updateBalance($refundAmount, FinanceRecord::TYPE_INCOME);

                // 2. Crear FinanceRecord de ingreso
                $financeRecord = FinanceRecord::create([
                    'type' => FinanceRecord::TYPE_INCOME,
                    'amount' => $refundAmount,
                    'description' => "Devolución de saldo a favor de proveedor: {$credit->supplier->name}",
                    'invoice_number' => 'DEV-CRED-' . $credit->id,
                    'user_id' => auth()->id() ?? 1,
                    'entry_date' => now()->toDateString(),
                ]);

                PaymentDistribution::create([
                    'finance_record_id' => $financeRecord->id,
                    'account_id' => $account->id,
                    'amount' => $refundAmount,
                    'payment_method' => 'Transferencia / Devolución',
                    'metadata' => [
                        'supplier_credit_balance_id' => $credit->id,
                        'supplier_id' => $credit->supplier_id,
                    ],
                ]);

                // 3. Actualizar el saldo restante
                $newUsed = (float) $credit->used_amount + $refundAmount;
                $newRemaining = max(0.0, (float) $credit->amount - $newUsed);
                $newStatus = $newRemaining <= 0.001 ? 'refunded' : 'partially_used';

                $credit->update([
                    'used_amount' => $newUsed,
                    'remaining_balance' => $newRemaining,
                    'status' => $newStatus,
                    'notes' => ($credit->notes ? $credit->notes . " | " : "") . "Reembolsado \${$refundAmount} a {$account->name} el " . date('Y-m-d'),
                ]);

                return $credit;
            });

            return response()->json([
                'success' => true,
                'message' => 'Reembolso procesado exitosamente.',
                'data' => $result,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el reembolso del saldo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
