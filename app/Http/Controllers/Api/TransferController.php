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
            Transfer::with(['fromAccount', 'toAccount'])->latest()->paginate(10)
        );
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
            $from->transactions()->create([
                'type' => 'expense',
                'amount' => $validated['amount'],
                'concept' => 'Transferencia enviada',
                'description' => $validated['description'],
                'transaction_date' => $validated['transfer_date'],
            ]);

            // ingreso
            $to->transactions()->create([
                'type' => 'income',
                'amount' => $validated['amount'],
                'concept' => 'Transferencia recibida',
                'description' => $validated['description'],
                'transaction_date' => $validated['transfer_date'],
            ]);

            return response()->json($transfer, 201);
        });
    }
}
