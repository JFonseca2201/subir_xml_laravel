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
        return response()->json(
            Transfer::with(['fromAccount', 'toAccount'])
                ->latest()
                ->paginate(10),
        );
    }

    /**
     * Get available accounts for transfers
     */
    public function getAvailableAccounts()
    {
        $accounts = \App\Models\Account::where('state', 1) // Activas
            ->select('id', 'name', 'current_balance', 'type')
            ->orderBy('name')
            ->get();

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

            $transfer = Transfer::create($validated);

            // egreso
            $fromDescription = $validated['description'] ?? 'Transferencia enviada';
            $toDescription = $validated['description'] ?? 'Transferencia recibida';

            // Ajustar descripción para caja chica
            if ($from->type === 'cash') {
                $fromDescription = 'Transferencia desde ' . $from->name;
            }
            if ($to->type === 'cash') {
                $toDescription = 'Transferencia a ' . $to->name;
            }

            \App\Models\AccountTransaction::create([
                'account_id' => $from->id,
                'type' => 'expense',
                'category' => 'transfer',
                'amount' => $validated['amount'],
                'description' => $fromDescription,
                'reference_id' => $transfer->id,
                'reference_type' => 'transfer',
                'transaction_date' => $validated['transfer_date'],
            ]);

            // ingreso
            \App\Models\AccountTransaction::create([
                'account_id' => $to->id,
                'type' => 'income',
                'category' => 'transfer',
                'amount' => $validated['amount'],
                'description' => $toDescription,
                'reference_id' => $transfer->id,
                'reference_type' => 'transfer',
                'transaction_date' => $validated['transfer_date'],
            ]);

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

            // Actualizar transferencia
            $transfer->update($validated);

            // Crear nuevas transacciones
            $fromDescription = $validated['description'] ?? 'Transferencia enviada';
            $toDescription = $validated['description'] ?? 'Transferencia recibida';

            // Ajustar descripción para caja chica
            if ($from->type === 'cash') {
                $fromDescription = 'Transferencia desde ' . $from->name;
            }
            if ($to->type === 'cash') {
                $toDescription = 'Transferencia a ' . $to->name;
            }

            \App\Models\AccountTransaction::create([
                'account_id' => $from->id,
                'type' => 'expense',
                'category' => 'transfer',
                'amount' => $validated['amount'],
                'description' => $fromDescription,
                'reference_id' => $transfer->id,
                'reference_type' => 'transfer',
                'transaction_date' => $validated['transfer_date'],
            ]);

            \App\Models\AccountTransaction::create([
                'account_id' => $to->id,
                'type' => 'income',
                'category' => 'transfer',
                'amount' => $validated['amount'],
                'description' => $toDescription,
                'reference_id' => $transfer->id,
                'reference_type' => 'transfer',
                'transaction_date' => $validated['transfer_date'],
            ]);

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

            // Devolver el monto a la cuenta de origen
            $fromAccount->increment('current_balance', $transfer->amount);

            // Eliminar transferencia
            $transfer->delete();

            return response()->json([
                'message' => 'Transferencia eliminada',
                'amount_returned' => $transfer->amount,
                'account_name' => $fromAccount->name,
                'new_balance' => $fromAccount->current_balance,
            ]);
        });
    }
}
