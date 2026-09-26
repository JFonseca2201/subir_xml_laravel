<?php

namespace App\Http\Controllers\Api\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder\WorkOrder;
use App\Models\WorkOrder\WorkOrderAdvance;
use App\Models\Finance\Account;
use App\Models\Finance\FinanceRecord;
use App\Models\Finance\PaymentDistribution;
use App\Models\Config\Sucursale;
use App\Helpers\PdfHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WorkOrderAdvanceController extends Controller
{
    /**
     * Listar los abonos/anticipos de una orden de trabajo.
     */
    public function index(int $workOrderId): JsonResponse
    {
        $workOrder = WorkOrder::with(['advances.account', 'advances.user', 'client', 'vehicle', 'items'])->findOrFail($workOrderId);

        return response()->json([
            'success' => true,
            'data' => [
                'work_order_id' => $workOrder->id,
                'work_order_number' => $workOrder->number,
                'total_amount' => $workOrder->total_amount,
                'total_advances' => $workOrder->total_advances,
                'balance_due' => $workOrder->balance_due,
                'advances' => $workOrder->advances,
            ]
        ]);
    }

    /**
     * Registrar un nuevo abono/anticipo.
     */
    public function store(Request $request, int $workOrderId): JsonResponse
    {
        $workOrder = WorkOrder::with(['client', 'vehicle', 'items', 'advances', 'sale'])->findOrFail($workOrderId);

        // Validar que la orden de trabajo no haya sido facturada/vendida
        if ($workOrder->sale && $workOrder->sale->status !== 'canceled' && $workOrder->sale->document_type !== 'quote') {
            return response()->json([
                'success' => false,
                'message' => 'No se pueden registrar nuevos abonos porque la orden de trabajo ya ha sido facturada/vendida.'
            ], 422);
        }

        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'advance_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ], [
            'account_id.required' => 'Debe seleccionar la cuenta a donde ingresa el dinero.',
            'amount.required' => 'El monto del abono es requerido.',
            'amount.min' => 'El monto debe ser mayor a 0.',
        ]);

        $amount = (float) $validated['amount'];
        $advanceDate = !empty($validated['advance_date']) 
            ? Carbon::parse($validated['advance_date'])->format('Y-m-d')
            : now('America/Guayaquil')->toDateString();

        $account = Account::findOrFail($validated['account_id']);
        $userId = auth('api')->id() ?? auth()->id() ?? 1;

        $clientName = $workOrder->client ? ($workOrder->client->full_name ?? ($workOrder->client->name . ' ' . $workOrder->client->surname)) : 'Cliente';

        $advance = DB::transaction(function () use ($workOrder, $account, $amount, $advanceDate, $validated, $userId, $clientName) {
            // 1. Generar número de recibo de abono
            $countAdvances = $workOrder->advances()->withTrashed()->count() + 1;
            $cleanOt = preg_replace('/[^0-9]/', '', $workOrder->number ?: (string)$workOrder->id);
            $receiptNumber = 'REC-ABONO-' . str_pad($cleanOt, 6, '0', STR_PAD_LEFT) . '-' . $countAdvances;

            // 2. Crear el registro en finanzas (FinanceRecord)
            $financeRecord = FinanceRecord::create([
                'entry_date' => $advanceDate,
                'type' => FinanceRecord::TYPE_INCOME,
                'account_id' => $account->id,
                'payment_method' => $validated['payment_method'],
                'amount' => $amount,
                'work_order_number' => $workOrder->number,
                'invoice_number' => $receiptNumber,
                'description' => "Abono / Anticipo OT: {$workOrder->number} - {$clientName}" . (!empty($validated['notes']) ? " ({$validated['notes']})" : ''),
                'user_id' => $userId,
            ]);

            // 3. Crear distribución de pago
            PaymentDistribution::create([
                'finance_record_id' => $financeRecord->id,
                'account_id' => $account->id,
                'amount' => $amount,
                'payment_method' => $validated['payment_method'],
            ]);

            // 4. Actualizar el saldo de la cuenta contable
            $account->updateBalance($amount, FinanceRecord::TYPE_INCOME);

            // 5. Registrar el movimiento financiero
            if (method_exists($workOrder, 'registerMovement')) {
                $workOrder->registerMovement(
                    $account->id,
                    'income',
                    $amount,
                    "Abono OT {$workOrder->number} - {$validated['payment_method']}",
                    $advanceDate,
                    [
                        'work_order_id' => $workOrder->id,
                        'work_order_number' => $workOrder->number,
                        'receipt_number' => $receiptNumber,
                        'finance_record_id' => $financeRecord->id,
                        'type' => 'work_order_advance'
                    ]
                );
            }

            // 6. Crear el registro de WorkOrderAdvance
            $advance = WorkOrderAdvance::create([
                'work_order_id' => $workOrder->id,
                'account_id' => $account->id,
                'finance_record_id' => $financeRecord->id,
                'amount' => $amount,
                'payment_method' => $validated['payment_method'],
                'advance_date' => $advanceDate,
                'receipt_number' => $receiptNumber,
                'notes' => $validated['notes'] ?? null,
                'user_id' => $userId,
            ]);

            return $advance;
        });

        $freshWorkOrder = $workOrder->fresh(['advances.account', 'advances.user', 'items']);

        return response()->json([
            'success' => true,
            'message' => 'Abono registrado exitosamente en la orden de trabajo y sumado a la cuenta de caja/banco.',
            'data' => [
                'advance' => $advance->load(['account', 'user']),
                'total_amount' => $freshWorkOrder->total_amount,
                'total_advances' => $freshWorkOrder->total_advances,
                'balance_due' => $freshWorkOrder->balance_due,
            ]
        ], 201);
    }

    /**
     * Eliminar un abono y revertir su impacto financiero.
     */
    public function destroy(int $workOrderId, int $advanceId): JsonResponse
    {
        $workOrder = WorkOrder::with(['advances', 'sale'])->findOrFail($workOrderId);

        // Si ya fue facturada y entregada, advertir
        if ($workOrder->sale && $workOrder->sale->status !== 'canceled') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede anular el abono porque esta orden de trabajo ya tiene una venta/factura activa asociada.'
            ], 400);
        }

        $advance = WorkOrderAdvance::where('work_order_id', $workOrderId)->findOrFail($advanceId);

        DB::transaction(function () use ($advance, $workOrder) {
            // Revertir saldo en la cuenta
            $account = Account::find($advance->account_id);
            if ($account) {
                $account->updateBalance($advance->amount, FinanceRecord::TYPE_EXPENSE);
            }

            // Eliminar registro financiero
            if ($advance->finance_record_id) {
                $fr = FinanceRecord::find($advance->finance_record_id);
                if ($fr) {
                    $fr->paymentDistributions()->delete();
                    $fr->delete();
                }
            }

            // Eliminar movimientos financieros asociados
            \App\Models\Finance\FinancialMovement::where('metadata->receipt_number', $advance->receipt_number)
                ->orWhere('metadata->work_order_advance_id', $advance->id)
                ->delete();

            $advance->delete();
        });

        $freshWorkOrder = $workOrder->fresh(['advances.account', 'advances.user', 'items']);

        return response()->json([
            'success' => true,
            'message' => 'Abono eliminado y saldo de cuenta revertido correctamente.',
            'data' => [
                'total_amount' => $freshWorkOrder->total_amount,
                'total_advances' => $freshWorkOrder->total_advances,
                'balance_due' => $freshWorkOrder->balance_due,
            ]
        ]);
    }

    /**
     * Imprimir recibo de abono en PDF.
     */
    public function printReceipt(int $workOrderId, int $advanceId)
    {
        $workOrder = WorkOrder::with(['client', 'vehicle', 'items', 'advances.account'])->findOrFail($workOrderId);
        $advance = WorkOrderAdvance::with(['account', 'user'])->where('work_order_id', $workOrderId)->findOrFail($advanceId);
        $sucursal = Sucursale::first();

        $data = [
            'workOrder' => $workOrder,
            'advance' => $advance,
            'sucursal' => $sucursal,
            'totalAmount' => $workOrder->total_amount,
            'totalAdvances' => $workOrder->total_advances,
            'balanceDue' => $workOrder->balance_due,
            'dateFormatted' => $advance->advance_date ? Carbon::parse($advance->advance_date)->format('d/m/Y') : now()->format('d/m/Y'),
        ];

        $pdf = Pdf::loadView('pdf.work_order_advance_receipt', $data);
        $pdf->setPaper([0, 0, 226.77, 450], 'portrait'); // 80mm ticket

        $fileName = "REC_ABONO_{$advance->receipt_number}.pdf";
        return $pdf->stream($fileName);
    }
}
