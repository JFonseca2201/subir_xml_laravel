<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashDenomination;
use App\Models\CashBalanceOperation;
use App\Models\Account;
use App\Models\DailyCashFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashBalanceController extends Controller
{
    /**
     * Abrir caja con conteo de denominaciones
     */
    public function openCash(Request $request)
    {
        $validated = $request->validate([
            'operation_date' => 'required|date',
            'account_id' => 'required|exists:accounts,id',
            'user_id' => 'required|exists:users,id',
            'bill_100_count' => 'required|integer|min:0',
            'bill_50_count' => 'required|integer|min:0',
            'bill_20_count' => 'required|integer|min:0',
            'bill_10_count' => 'required|integer|min:0',
            'bill_5_count' => 'required|integer|min:0',
            'bill_1_count' => 'required|integer|min:0',
            'coin_1_count' => 'required|integer|min:0',
            'coin_50_count' => 'required|integer|min:0',
            'coin_25_count' => 'required|integer|min:0',
            'coin_10_count' => 'required|integer|min:0',
            'coin_5_count' => 'required|integer|min:0',
            'coin_1_cent_count' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($validated) {
            // Obtener balance actual del sistema
            $account = Account::find($validated['account_id']);
            $systemBalance = $account->current_balance ?? 0;

            // Crear registro de denominaciones
            $denomination = CashDenomination::create([
                'operation_type' => 'opening',
                'operation_date' => $validated['operation_date'],
                'account_id' => $validated['account_id'],
                'user_id' => $validated['user_id'],
                'bill_100_count' => $validated['bill_100_count'],
                'bill_50_count' => $validated['bill_50_count'],
                'bill_20_count' => $validated['bill_20_count'],
                'bill_10_count' => $validated['bill_10_count'],
                'bill_5_count' => $validated['bill_5_count'],
                'bill_1_count' => $validated['bill_1_count'],
                'coin_1_count' => $validated['coin_1_count'],
                'coin_50_count' => $validated['coin_50_count'],
                'coin_25_count' => $validated['coin_25_count'],
                'coin_10_count' => $validated['coin_10_count'],
                'coin_5_count' => $validated['coin_5_count'],
                'coin_1_cent_count' => $validated['coin_1_cent_count'],
                'system_balance' => $systemBalance,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Crear operación de balance
            $balanceOperation = CashBalanceOperation::create([
                'operation_type' => 'opening',
                'operation_date' => $validated['operation_date'],
                'account_id' => $validated['account_id'],
                'user_id' => $validated['user_id'],
                'system_balance' => $systemBalance,
                'expected_balance' => $denomination->total_cash,
                'actual_balance' => $denomination->total_cash,
                'difference' => $denomination->difference,
                'cash_denomination_id' => $denomination->id,
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json([
                'status' => 201,
                'message' => 'Caja abierta correctamente',
                'denomination' => $denomination->load(['account', 'user']),
                'balance_operation' => $balanceOperation->load(['account', 'user']),
            ], 201);
        });
    }

    /**
     * Cerrar caja con conteo de denominaciones y balance
     */
    public function closeCash(Request $request)
    {
        $validated = $request->validate([
            'operation_date' => 'required|date',
            'account_id' => 'required|exists:accounts,id',
            'user_id' => 'required|exists:users,id',
            'bill_100_count' => 'required|integer|min:0',
            'bill_50_count' => 'required|integer|min:0',
            'bill_20_count' => 'required|integer|min:0',
            'bill_10_count' => 'required|integer|min:0',
            'bill_5_count' => 'required|integer|min:0',
            'bill_1_count' => 'required|integer|min:0',
            'coin_1_count' => 'required|integer|min:0',
            'coin_50_count' => 'required|integer|min:0',
            'coin_25_count' => 'required|integer|min:0',
            'coin_10_count' => 'required|integer|min:0',
            'coin_5_count' => 'required|integer|min:0',
            'coin_1_cent_count' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($validated) {
            // Obtener balance actual del sistema
            $account = Account::find($validated['account_id']);
            $systemBalance = $account->current_balance ?? 0;

            // Crear registro de denominaciones
            $denomination = CashDenomination::create([
                'operation_type' => 'closing',
                'operation_date' => $validated['operation_date'],
                'account_id' => $validated['account_id'],
                'user_id' => $validated['user_id'],
                'bill_100_count' => $validated['bill_100_count'],
                'bill_50_count' => $validated['bill_50_count'],
                'bill_20_count' => $validated['bill_20_count'],
                'bill_10_count' => $validated['bill_10_count'],
                'bill_5_count' => $validated['bill_5_count'],
                'bill_1_count' => $validated['bill_1_count'],
                'coin_1_count' => $validated['coin_1_count'],
                'coin_50_count' => $validated['coin_50_count'],
                'coin_25_count' => $validated['coin_25_count'],
                'coin_10_count' => $validated['coin_10_count'],
                'coin_5_count' => $validated['coin_5_count'],
                'coin_1_cent_count' => $validated['coin_1_cent_count'],
                'system_balance' => $systemBalance,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Determinar estado del balance
            $status = 'completed';
            if (abs($denomination->difference) > 0.01) {
                $status = 'pending'; // Requiere conciliación
            }

            // Crear operación de balance
            $balanceOperation = CashBalanceOperation::create([
                'operation_type' => 'closing',
                'operation_date' => $validated['operation_date'],
                'account_id' => $validated['account_id'],
                'user_id' => $validated['user_id'],
                'system_balance' => $systemBalance,
                'expected_balance' => $systemBalance,
                'actual_balance' => $denomination->total_cash,
                'difference' => $denomination->difference,
                'cash_denomination_id' => $denomination->id,
                'status' => $status,
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json([
                'status' => 201,
                'message' => 'Caja cerrada correctamente',
                'denomination' => $denomination->load(['account', 'user']),
                'balance_operation' => $balanceOperation->load(['account', 'user']),
                'balance_status' => [
                    'status' => $denomination->balance_status,
                    'description' => $denomination->balance_status_description,
                    'difference' => $denomination->formatted_difference,
                ],
            ], 201);
        });
    }

    /**
     * Obtener balance general de la compañía
     */
    public function getCompanyBalance(Request $request)
    {
        $date = $request->get('date', now()->format('Y-m-d'));

        // Obtener todas las cuentas activas
        $accounts = Account::where('state', 1)->get();

        $balanceData = [];
        $totalCompanyBalance = 0;

        foreach ($accounts as $account) {
            // Obtener balance actual
            $currentBalance = $account->current_balance ?? 0;

            // Obtener operaciones del día
            $dayOperations = DailyCashFlow::whereDate('flow_date', $date)
                ->where('account_id', $account->id)
                ->get();

            $dayIncome = $dayOperations->where('flow_type', 'income')->sum('total_amount');
            $dayExpense = $dayOperations->where('flow_type', 'expense')->sum('total_amount');
            $dayBalance = $dayIncome - $dayExpense;

            // Obtener última operación de balance
            $lastBalanceOp = CashBalanceOperation::where('account_id', $account->id)
                ->orderBy('operation_date', 'desc')
                ->first();

            $balanceData[] = [
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                ],
                'current_balance' => $currentBalance,
                'day_income' => $dayIncome,
                'day_expense' => $dayExpense,
                'day_balance' => $dayBalance,
                'last_balance_operation' => $lastBalanceOp ? [
                    'type' => $lastBalanceOp->operation_type,
                    'date' => $lastBalanceOp->operation_date,
                    'difference' => $lastBalanceOp->difference,
                    'status' => $lastBalanceOp->status,
                ] : null,
            ];

            $totalCompanyBalance += $currentBalance;
        }

        return response()->json([
            'date' => $date,
            'total_company_balance' => $totalCompanyBalance,
            'accounts_balance' => $balanceData,
            'account_count' => count($accounts),
        ]);
    }

    /**
     * Obtener historial de operaciones de caja
     */
    public function getBalanceHistory(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'nullable|exists:accounts,id',
            'operation_type' => 'nullable|in:opening,closing',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $query = CashBalanceOperation::with(['account', 'user', 'cashDenomination'])
            ->orderBy('operation_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Aplicar filtros
        if (isset($validated['account_id'])) {
            $query->where('account_id', $validated['account_id']);
        }

        if (isset($validated['operation_type'])) {
            $query->where('operation_type', $validated['operation_type']);
        }

        if (isset($validated['start_date'])) {
            $query->whereDate('operation_date', '>=', $validated['start_date']);
        }

        if (isset($validated['end_date'])) {
            $query->whereDate('operation_date', '<=', $validated['end_date']);
        }

        $operations = $query->paginate(20);

        return response()->json([
            'operations' => $operations->items(),
            'pagination' => [
                'total' => $operations->total(),
                'count' => $operations->count(),
                'per_page' => $operations->perPage(),
                'current_page' => $operations->currentPage(),
                'total_pages' => $operations->lastPage(),
            ],
        ]);
    }

    /**
     * Obtener detalles de una operación de caja específica
     */
    public function getBalanceOperationDetails(CashBalanceOperation $balanceOperation)
    {
        $balanceOperation->load(['account', 'user', 'cashDenomination']);

        // Si tiene denominaciones, agregar detalles
        if ($balanceOperation->cashDenomination) {
            $denomination = $balanceOperation->cashDenomination;
            $balanceOperation->denominations = $denomination->getDenominationsArray();
            $balanceOperation->balance_status = $denomination->balance_status;
            $balanceOperation->balance_status_description = $denomination->balance_status_description;
        }

        return response()->json([
            'operation' => $balanceOperation,
        ]);
    }

    /**
     * Obtener cuentas disponibles para balance
     */
    public function getAvailableAccounts()
    {
        $accounts = Account::where('state', 1)
            ->select('id', 'name', 'type', 'current_balance')
            ->orderBy('name')
            ->get();

        return response()->json([
            'accounts' => $accounts->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'current_balance' => $account->current_balance ?? 0,
                    'formatted_balance' => '$' . number_format($account->current_balance ?? 0, 2),
                ];
            }),
        ]);
    }
}
