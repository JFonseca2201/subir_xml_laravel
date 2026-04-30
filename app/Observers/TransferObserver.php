<?php

namespace App\Observers;

use App\Models\AccountTransaction;
use App\Models\DailyCashFlow;
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
            $toAccount = $transfer->toAccount;

            // Eliminar registros de DailyCashFlow asociados
            DailyCashFlow::where('source_type', 'transfer')
                ->where('source_id', $transfer->id)
                ->delete();

            // Eliminar transacciones asociadas
            AccountTransaction::where('reference_type', 'transfer')
                ->where('reference_id', $transfer->id)
                ->delete();

            // Devolver el monto a la cuenta de origen
            $fromAccount->update([
                'current_balance' => $fromAccount->current_balance + $transfer->amount
            ]);

            // Quitar el monto de la cuenta de destino
            $toAccount->update([
                'current_balance' => $toAccount->current_balance - $transfer->amount
            ]);
        });
    }
}
