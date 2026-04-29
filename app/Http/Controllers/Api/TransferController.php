<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transfer;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Services\AccountService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    public function index()
    {
        $transfers = Transfer::with(['fromAccount', 'toAccount', 'user'])
            ->latest()
            ->paginate(10);

        return response()->json($transfers);
    }

    /**
     * Get available accounts for transfers
     */
    public function getAvailableAccounts()
    {
        // Obtener todas las cuentas activas (no solo bancarias)
        $accounts = \App\Models\Account::where('state', true) // Activas
            ->select('id', 'name', 'current_balance', 'initial_balance', 'type', 'bank_name')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        // Calcular balances reales basados en todas las transacciones
        $accounts = $accounts->map(function ($account) {
            // Calcular balance real basado en transacciones
            $realBalance = $this->calculateAccountBalance($account->id);

            return [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'bank_name' => $account->bank_name,
                'balance' => $realBalance, // Balance calculado real
                'current_balance' => $account->current_balance ?? $account->initial_balance ?? 0,
                'description' => AccountService::getDescripcionTipoCuenta($account),
            ];
        });

        return response()->json([
            'accounts' => $accounts,
            'message' => 'Cuentas disponibles con balances calculados'
        ]);
    }

    /**
     * Calculate real account balance based on all transactions
     */
    private function calculateAccountBalance($accountId)
    {
        // Obtener todos los movimientos que afectan esta cuenta
        $income = \App\Models\DailyCashFlow::where('account_id', $accountId)
            ->where('flow_type', 'income')
            ->sum('total_amount');

        $expense = \App\Models\DailyCashFlow::where('account_id', $accountId)
            ->where('flow_type', 'expense')
            ->sum('total_amount');

        // Para transferencias, considerar solo las que afectan esta cuenta
        $transferIn = \App\Models\Transfer::where('to_account_id', $accountId)->sum('amount');
        $transferOut = \App\Models\Transfer::where('from_account_id', $accountId)->sum('amount');

        // Balance = ingresos - egresos + transferencias entrantes - transferencias salientes
        return $income - $expense + $transferIn - $transferOut;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_account_id' => 'required|exists:accounts,id',
            'to_account_id' => 'required|exists:accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'transfer_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated) {
            $from = \App\Models\Account::findOrFail($validated['from_account_id']);
            $to = \App\Models\Account::findOrFail($validated['to_account_id']);

            // Validar que las transferencias sean entre cuentas del mismo tipo o compatibles
            // Permitir transferencias bancarias y de caja chica
            $validTransferTypes = [
                'bank' => ['bank', 'cash'], // Desde bancos puede transferir a bancos o caja
                'cash' => ['bank', 'cash']  // Desde caja puede transferir a bancos o caja
            ];

            if (!in_array($to->type, $validTransferTypes[$from->type])) {
                return response()->json([
                    'message' => 'Tipo de transferencia no permitida',
                    'from_account_type' => $from->type,
                    'to_account_type' => $to->type,
                    'allowed_types' => $validTransferTypes[$from->type],
                ], 422);
            }

            if ($from->current_balance < $validated['amount']) {
                return response()->json([
                    'message' => 'Saldo insuficiente',
                    'from_account' => $from->name,
                    'current_balance' => $from->current_balance,
                    'required_amount' => $validated['amount'],
                ], 422);
            }

            // Crear transferencia sin generar transacciones (el Observer lo hará)
            $transfer = Transfer::create($validated);

            return response()->json($transfer, 201);
        });
    }

    public function update(Request $request, Transfer $transfer)
    {
        $validated = $request->validate([
            'from_account_id' => 'required|exists:accounts,id',
            'to_account_id' => 'required|exists:accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'transfer_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $transfer) {
            $from = \App\Models\Account::findOrFail($validated['from_account_id']);
            $to = \App\Models\Account::findOrFail($validated['to_account_id']);

            // Validar saldo suficiente si cambió la cuenta de origen o el monto
            if ($validated['from_account_id'] != $transfer->from_account_id || $validated['amount'] != $transfer->amount) {
                if ($from->current_balance < $validated['amount']) {
                    return response()->json(['message' => 'Saldo insuficiente'], 422);
                }
            }

            // Eliminar transacciones antiguas
            \App\Models\AccountTransaction::where('reference_type', 'transfer')
                ->where('reference_id', $transfer->id)
                ->delete();

            // Actualizar transferencia (el Observer creará nuevas transacciones)
            $transfer->update($validated);

            return response()->json($transfer->fresh(['fromAccount', 'toAccount']));
        });
    }

    public function destroy(Transfer $transfer)
    {
        return DB::transaction(function () use ($transfer) {
            // Obtener la cuenta de origen
            $fromAccount = \App\Models\Account::findOrFail($transfer->from_account_id);

            // Eliminar transacciones asociadas
            \App\Models\AccountTransaction::where('reference_type', 'transfer')
                ->where('reference_id', $transfer->id)
                ->delete();

            // Devolver el monto a la cuenta de origen con precisión decimal exacta
            $fromAccount->update([
                'current_balance' => $fromAccount->current_balance + $transfer->amount
            ]);

            // Eliminar transferencia
            $transfer->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Transferencia eliminada correctamente',
                'deleted_transfer_id' => $transfer->id,
                'amount_returned' => $transfer->amount,
                'account_name' => $fromAccount->name,
                'new_balance' => $fromAccount->current_balance,
            ]);
        });
    }
}
