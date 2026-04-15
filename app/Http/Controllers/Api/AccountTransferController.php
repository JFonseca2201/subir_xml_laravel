<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountTransferController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'origin_account_id' => 'required|integer|exists:accounts,id',
            'destination_account_id' => 'required|integer|exists:accounts,id|different:origin_account_id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'transfer_date' => 'required|date',
        ], [
            'origin_account_id.required' => 'La cuenta de origen es obligatoria.',
            'origin_account_id.exists' => 'La cuenta de origen no existe.',
            'destination_account_id.required' => 'La cuenta de destino es obligatoria.',
            'destination_account_id.exists' => 'La cuenta de destino no existe.',
            'destination_account_id.different' => 'Las cuentas de origen y destino deben ser diferentes.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.min' => 'El monto debe ser mayor a 0.',
            'transfer_date.required' => 'La fecha de transferencia es obligatoria.',
        ]);

        return DB::transaction(function () use ($validated) {
            // 1. Obtener cuentas con bloqueo para evitar race conditions
            $originAccount = Account::lockForUpdate()->findOrFail($validated['origin_account_id']);
            $destinationAccount = Account::lockForUpdate()->findOrFail($validated['destination_account_id']);

            // 2. Validar saldo suficiente en cuenta de origen
            if ($originAccount->current_balance < $validated['amount']) {
                return response()->json([
                    'status' => 422,
                    'message' => 'Saldo insuficiente en la cuenta de origen',
                    'origin_account' => [
                        'name' => $originAccount->name,
                        'current_balance' => $originAccount->current_balance,
                    ],
                    'required_amount' => $validated['amount'],
                    'shortfall' => $validated['amount'] - $originAccount->current_balance,
                ], 422);
            }

            // 3. Generar UUID para agrupar las transacciones
            $transferGroupId = Str::uuid();

            // 4. Crear transacción de egreso en cuenta origen
            $originTransaction = AccountTransaction::create([
                'account_id' => $originAccount->id,
                'type' => 'expense',
                'category' => 'transfer',
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? 'Transferencia a ' . $destinationAccount->name,
                'transaction_date' => $validated['transfer_date'],
                'transfer_group_id' => $transferGroupId,
            ]);

            // 5. Crear transacción de ingreso en cuenta destino
            $destinationTransaction = AccountTransaction::create([
                'account_id' => $destinationAccount->id,
                'type' => 'income',
                'category' => 'transfer',
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? 'Transferencia desde ' . $originAccount->name,
                'transaction_date' => $validated['transfer_date'],
                'transfer_group_id' => $transferGroupId,
            ]);

            // 6. Actualizar saldos de ambas cuentas
            $originAccount->decrement('current_balance', $validated['amount']);
            $destinationAccount->increment('current_balance', $validated['amount']);

            return response()->json([
                'status' => 201,
                'message' => 'Transferencia realizada exitosamente',
                'transfer_group_id' => $transferGroupId,
                'origin_transaction' => $originTransaction->load('account'),
                'destination_transaction' => $destinationTransaction->load('account'),
                'origin_account' => [
                    'id' => $originAccount->id,
                    'name' => $originAccount->name,
                    'previous_balance' => $originAccount->current_balance + $validated['amount'],
                    'new_balance' => $originAccount->current_balance,
                ],
                'destination_account' => [
                    'id' => $destinationAccount->id,
                    'name' => $destinationAccount->name,
                    'previous_balance' => $destinationAccount->current_balance - $validated['amount'],
                    'new_balance' => $destinationAccount->current_balance,
                ],
            ], 201);
        });
    }

    public function index(Request $request)
    {
        $transfers = AccountTransaction::with(['account'])
            ->where('category', 'transfer')
            ->whereNotNull('transfer_group_id')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Agrupar por transfer_group_id para mostrar transferencias completas
        $groupedTransfers = $transfers->getCollection()->groupBy('transfer_group_id');

        return response()->json([
            'transfers' => $groupedTransfers,
            'pagination' => [
                'total' => $transfers->total(),
                'count' => $transfers->count(),
                'per_page' => $transfers->perPage(),
                'current_page' => $transfers->currentPage(),
                'total_pages' => $transfers->lastPage(),
            ],
        ]);
    }
}
