<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinanceRecordRequest;
use App\Http\Resources\FinanceRecordResource;
use App\Models\FinanceRecord;
use App\Models\Account;
use App\Models\PaymentDistribution;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = FinanceRecord::orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by account if provided
        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        // Filter by date range if provided
        if ($request->has('start_date')) {
            $query->whereDate('entry_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('entry_date', '<=', $request->end_date);
        }

        $records = $query->with(['paymentDistributions.account'])->get();

        return FinanceRecordResource::collection($records);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFinanceRecordRequest $request): JsonResponse
{
    return DB::transaction(function () use ($request) {
        $data = $request->validated();

        $entryDate = $data['entry_date'] ?? Carbon::now('America/Guayaquil')->format('Y-m-d');

        $payments = $data['payments'] ?? [];
        unset($data['payments']);

        // 1. Crear el registro principal
        $financeRecord = FinanceRecord::create([
            'entry_date' => $entryDate,
            'type' => $data['type'],
            'description' => $data['description'],
            'amount' => array_sum(array_column($payments, 'amount')),
            'user_id' => Auth::id(),
            'work_order_number' => $data['work_order_number'] ?? null,
            'invoice_number' => $data['invoice_number'] ?? null,
        ]);

        $paymentDistributions = [];
        foreach ($payments as $payment) {
            // 2. Crear la distribución
            $distribution = PaymentDistribution::create([
                'finance_record_id' => $financeRecord->id,
                'account_id' => $payment['account_id'],
                'amount' => $payment['amount'],
                'payment_method' => $this->getPaymentMethod($payment['account_id']),
            ]);

            // --- IMPLEMENTACIÓN DEL TRAIT ---
            // Registramos el movimiento vinculado a la distribución
            $distribution->registerMovement(
                $payment['account_id'],
                (int)$data['type'] === 0 ? 'income' : 'expense', // Seguro estricto del tipo
                $payment['amount'],
                $data['description'], // Descripción general del registro
                $entryDate,
                [
                    'finance_record_id' => $financeRecord->id,
                    'record_type' => (int)$data['type'],
                    'invoice' => $financeRecord->invoice_number,
                    'work_order' => $financeRecord->work_order_number
                ]
            );

            $paymentDistributions[] = $distribution;

            // 3. Actualizar saldo
            $account = Account::find($payment['account_id']);
            if ($account) {
                $account->updateBalance($payment['amount'], $data['type']);
            }
        }

        $financeRecord->load('paymentDistributions.account');

        return response()->json([
            'message' => 'Finance record created successfully',
            'data' => new FinanceRecordResource($financeRecord)
        ], 201);
    });
}

    /**
     * Determinar método de pago según account_id
     */
    private function getPaymentMethod($accountId): string
    {
        return match ($accountId) {
            1 => 'cash',
            2, 3 => 'transfer',
            default => 'cash'
        };
    }

    /**
     * Display the specified resource.
     */
    public function show(FinanceRecord $financeRecord): FinanceRecordResource
    {
        $financeRecord->load(['user', 'paymentDistributions.account']);
        return new FinanceRecordResource($financeRecord);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreFinanceRecordRequest $request, FinanceRecord $financeRecord): JsonResponse
{
    return DB::transaction(function () use ($request, $financeRecord) {
        $data = $request->validated();
        $payments = $data['payments'];

        // 1. Revertir saldos y limpiar movimientos financieros antiguos
        $existingDistributions = $financeRecord->paymentDistributions;
        foreach ($existingDistributions as $distribution) {
            $account = Account::find($distribution->account_id);
            if ($account) {
                // Revertir saldo original (lógica existente)
                if ($financeRecord->type === 0) {
                    $account->updateBalance($distribution->amount, 1);
                } else {
                    $account->updateBalance($distribution->amount, 0);
                }
            }
            
            // ELIMINAR el movimiento financiero asociado a la distribución antigua
            // Esto evita que el Dashboard muestre datos duplicados tras la edición
            $distribution->financialMovement()->delete();
        }

        // 2. Eliminar distribuciones existentes (Base de datos)
        $financeRecord->paymentDistributions()->delete();

        // 3. Crear nuevas distribuciones y registrar nuevos movimientos
        $totalAmount = 0;
        foreach ($payments as $paymentData) {
            $newDistribution = PaymentDistribution::create([
                'finance_record_id' => $financeRecord->id,
                'account_id' => $paymentData['account_id'],
                'amount' => $paymentData['amount'],
                'payment_method' => $this->getPaymentMethod($paymentData['account_id'])
            ]);

            // --- IMPLEMENTACIÓN DEL TRAIT ---
            // Registramos el nuevo movimiento para la distribución recién creada
            $newDistribution->registerMovement(
                $paymentData['account_id'],
                (int)$data['type'] === 0 ? 'income' : 'expense', // Seguro estricto del tipo
                $paymentData['amount'],
                $data['description'],
                $data['entry_date'],
                [
                    'finance_record_id' => $financeRecord->id,
                    'record_type' => (int)$data['type'],
                    'info' => 'Registro editado',
                    'invoice' => $data['invoice_number'] ?? null
                ]
            );

            // Actualizar saldo de la cuenta
            $account = Account::find($paymentData['account_id']);
            if ($account) {
                $account->updateBalance($paymentData['amount'], $data['type']);
            }

            $totalAmount += $paymentData['amount'];
        }

        // 4. Actualizar el registro principal
        $financeRecord->update([
            'type' => $data['type'],
            'work_order_number' => $data['work_order_number'] ?? null,
            'invoice_number' => $data['invoice_number'] ?? null,
            'description' => $data['description'],
            'entry_date' => $data['entry_date'],
            'amount' => $totalAmount
        ]);

        $financeRecord->load(['user', 'paymentDistributions.account']);

        return response()->json([
            'message' => 'Finance record updated successfully',
            'data' => new FinanceRecordResource($financeRecord)
        ]);
    });
}

    /**
     * Remove the specified resource from storage.
     */
   public function destroy(FinanceRecord $financeRecord): JsonResponse
{
    return DB::transaction(function () use ($financeRecord) {
        $type = $financeRecord->type;

        // 1. Obtener distribuciones de pago
        $paymentDistributions = $financeRecord->paymentDistributions;

        if ($paymentDistributions->count() > 0) {
            // --- NUEVO SISTEMA: DISTRIBUCIONES ---
            foreach ($paymentDistributions as $distribution) {
                // ELIMINAR el movimiento financiero del Dashboard primero
                $distribution->financialMovement()->delete();

                $account = Account::find($distribution->account_id);
                if ($account) {
                    // Revertir el saldo (lógica existente)
                    if ($type === 0) {
                        $account->updateBalance($distribution->amount, 1);
                    } else {
                        $account->updateBalance($distribution->amount, 0);
                    }
                }
            }
        } else {
            // --- SISTEMA ANTIGUO: CUENTA PRINCIPAL ---
            // Nota: Si el sistema antiguo no usaba el Trait, esta línea no hará nada (lo cual está bien)
            // Pero si lo usaba, lo limpiamos también.
            if (method_exists($financeRecord, 'financialMovement')) {
                $financeRecord->financialMovement()->delete();
            }

            $account = Account::find($financeRecord->account_id);
            if ($account) {
                if ($type === 0) {
                    $account->updateBalance($financeRecord->amount, 1);
                } else {
                    $account->updateBalance($financeRecord->amount, 0);
                }
            }
        }

        // 2. Eliminar el registro (las distribuciones se eliminan por cascada en BD o manualmente)
        $financeRecord->delete();

        return response()->json([
            'message' => 'Finance record deleted successfully'
        ]);
    });
}

    /**
     * Remove the specified payment distribution from storage.
     */
    public function destroyPaymentDistribution(PaymentDistribution $paymentDistribution): JsonResponse
{
    return DB::transaction(function () use ($paymentDistribution) {
        // 1. Obtener datos antes de eliminar
        $financeRecord = $paymentDistribution->financeRecord;
        $accountId = $paymentDistribution->account_id;
        $amount = $paymentDistribution->amount;
        $type = $financeRecord->type;

        // --- IMPLEMENTACIÓN DEL TRAIT ---
        // Eliminamos el movimiento financiero asociado a esta distribución específica
        // Esto limpia el historial financiero/dashboard inmediatamente
        $paymentDistribution->financialMovement()->delete();

        // 2. Actualizar saldo de la cuenta (revertir el movimiento)
        $account = Account::find($accountId);
        if ($account) {
            // Si era ingreso (type=0), restar del saldo
            // Si era egreso (type=1), sumar al saldo
            if ($type === 0) {
                $account->updateBalance($amount, 1);
            } else {
                $account->updateBalance($amount, 0);
            }
        }

        // 3. Eliminar la distribución de pago
        $paymentDistribution->delete();

        // 4. Actualizar el monto total del finance_record principal
        // Es importante hacer el sum() después del delete() para obtener el total real
        $remainingTotal = $financeRecord->paymentDistributions()->sum('amount');
        $financeRecord->update(['amount' => $remainingTotal]);

        return response()->json([
            'message' => 'Payment distribution deleted successfully',
            'data' => [
                'remaining_amount' => (float) $remainingTotal,
                'deleted_amount' => (float) $amount
            ]
        ]);
    });
}

    /**
     * Get daily summary grouped by entry_date.
     */
    public function dailySummary(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', Carbon::now('America/Guayaquil')->subDays(30)->toDateString());
        $endDate = $request->get('end_date', Carbon::now('America/Guayaquil')->toDateString());

        $summary = FinanceRecord::selectRaw('
                entry_date,
                SUM(CASE WHEN type = 0 THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) as total_expenses,
                COUNT(CASE WHEN type = 0 THEN 1 END) as income_count,
                COUNT(CASE WHEN type = 1 THEN 1 END) as expense_count,
                COUNT(*) as total_transactions
            ')
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->groupBy('entry_date')
            ->orderBy('entry_date', 'desc')
            ->get()
            ->map(function ($item) {
                $item->balance = $item->total_income - $item->total_expenses;
                $item->total_income = (float) $item->total_income;
                $item->total_expenses = (float) $item->total_expenses;
                $item->balance = (float) $item->balance;
                $item->total_transactions = (int) $item->total_transactions;
                $item->income_count = (int) $item->income_count;
                $item->expense_count = (int) $item->expense_count;
                return $item;
            });

        return response()->json([
            'data' => $summary,
            'totals' => [
                'total_incomes' => $summary->sum('total_income'),
                'total_expenses' => $summary->sum('total_expenses'),
                'total_balance' => $summary->sum('balance'),
            ],
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get movements grouped by work order.
     */
    public function groupedByWorkOrder(Request $request): JsonResponse
    {
        $type = $request->get('type', 0); // 0 = ingresos, 1 = egresos

        $groupedMovements = FinanceRecord::with('account')->where('type', $type)
            ->whereNotNull('work_order_number')
            ->orderBy('work_order_number')
            ->orderBy('entry_date', 'desc')
            ->get()
            ->groupBy('work_order_number')
            ->map(function ($group, $workOrderNumber) {
                $total = $group->sum('amount');
                $firstRecord = $group->first();

                return [
                    'work_order_number' => $workOrderNumber,
                    'description' => $firstRecord->description,
                    'entry_date' => $firstRecord->entry_date,
                    'total_amount' => $total,
                    'movements_count' => $group->count(),
                    'movements' => $group->map(function ($movement) {
                        return [
                            'id' => $movement->id,
                            'account_name' => $movement->account->name ?? 'N/A',
                            'payment_method' => $movement->payment_method,
                            'amount' => $movement->amount,
                            'entry_date' => $movement->entry_date,
                            'created_at' => $movement->created_at
                        ];
                    })->toArray()
                ];
            })
            ->values()
            ->sortByDesc('entry_date');

        return response()->json([
            'data' => $groupedMovements->values(),
            'total_work_orders' => $groupedMovements->count(),
            'total_amount' => $groupedMovements->sum('total_amount')
        ]);
    }

    /**
     * Get statistics for the current month.
     */
    public function monthlyStats(): JsonResponse
    {
        $currentMonth = now('America/Guayaquil')->startOfMonth();
        $endOfMonth = now('America/Guayaquil')->endOfMonth();

        $stats = FinanceRecord::selectRaw('
                SUM(CASE WHEN type = 0 THEN amount ELSE 0 END) as total_incomes,
                SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) as total_expenses,
                COUNT(CASE WHEN type = 0 THEN 1 END) as income_count,
                COUNT(CASE WHEN type = 1 THEN 1 END) as expense_count
            ')
            ->whereDate('entry_date', '>=', $currentMonth)
            ->whereDate('entry_date', '<=', $endOfMonth)
            ->first();

        return response()->json([
            'data' => $stats,
            'period' => [
                'month' => $currentMonth->format('F Y'),
                'start_date' => $currentMonth->toDateString(),
                'end_date' => $endOfMonth->toDateString(),
            ]
        ]);
    }
}