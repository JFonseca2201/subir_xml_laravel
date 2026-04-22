<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transfer;
use Illuminate\Http\Request;
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
        $accounts = \App\Models\Account::where('state', 1) // Activas
            ->select('id', 'name', 'current_balance', 'initial_balance', 'type')
            ->orderBy('name')
            ->get();

        // Mapear para usar current_balance o initial_balance como fallback
        $accounts = $accounts->map(function ($account) {
            return [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'current_balance' => $account->current_balance ?? $account->initial_balance ?? 0,
            ];
        });

        return response()->json([
            'status' => 200,
            'accounts' => $accounts,
            'total_accounts' => $accounts->count(),
        ]);
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

            if ($from->current_balance < $validated['amount']) {
                return response()->json(['message' => 'Saldo insuficiente'], 422);
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
