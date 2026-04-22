<?php

namespace App\Observers;

use App\Models\AccountTransaction;
use Illuminate\Support\Facades\DB;

class TransferObserver
{
    /**
     * Handle the Transfer "created" event.
     */
    public function created($transfer)
    {
        DB::transaction(function () use ($transfer) {
            $fromAccount = $transfer->fromAccount;
            $toAccount = $transfer->toAccount;

            // Ajustar descripción para caja chica
            $fromDescription = $transfer->description ?? 'Transferencia enviada';
            $toDescription = $transfer->description ?? 'Transferencia recibida';

            if ($fromAccount->type === 'cash') {
                $fromDescription = 'Transferencia desde ' . $fromAccount->name;
            }
            if ($toAccount->type === 'cash') {
                $toDescription = 'Transferencia a ' . $toAccount->name;
            }

            // Crear transacción de egreso en cuenta origen
            AccountTransaction::create([
                'account_id' => $fromAccount->id,
                'type' => 'expense',
                'category' => 'transfer',
                'amount' => $transfer->amount,
                'description' => $fromDescription,
                'reference_id' => $transfer->id,
                'reference_type' => 'transfer',
                'transaction_date' => $transfer->transfer_date,
            ]);

            // Crear transacción de ingreso en cuenta destino
            AccountTransaction::create([
                'account_id' => $toAccount->id,
                'type' => 'income',
                'category' => 'transfer',
                'amount' => $transfer->amount,
                'description' => $toDescription,
                'reference_id' => $transfer->id,
                'reference_type' => 'transfer',
                'transaction_date' => $transfer->transfer_date,
            ]);

            // Actualizar saldos de las cuentas con precisión decimal exacta
            $fromAccount->update([
                'current_balance' => $fromAccount->current_balance - $transfer->amount
            ]);
            $toAccount->update([
                'current_balance' => $toAccount->current_balance + $transfer->amount
            ]);
        });
    }

    /**
     * Handle the Transfer "deleted" event.
     */
    public function deleted($transfer)
    {
        DB::transaction(function () use ($transfer) {
            $fromAccount = $transfer->fromAccount;

            // Eliminar transacciones asociadas
            AccountTransaction::where('reference_type', 'transfer')
                ->where('reference_id', $transfer->id)
                ->delete();

            // Devolver el monto a la cuenta de origen con precisión decimal exacta
            $fromAccount->update([
                'current_balance' => $fromAccount->current_balance + $transfer->amount
            ]);
        });
    }
}
