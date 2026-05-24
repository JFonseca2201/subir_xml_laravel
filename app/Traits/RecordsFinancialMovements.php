<?php

namespace App\Traits;

use App\Models\FinancialMovement;
use Illuminate\Database\Eloquent\Model;

trait RecordsFinancialMovements
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function financialMovement()
    {
        /** @var Model $this */ // Esto le dice al editor que trate a $this como un modelo
        return $this->morphOne(FinancialMovement::class, 'movable');
    }
    /**
     * Método para registrar o actualizar el movimiento.
     */
    public function registerMovement($accountId, $type, $amount, $description, $entryDate, $metadata = [])
    {
        return FinancialMovement::create([
            'movable_id' => $this->id,
            'movable_type' => get_class($this),
            'account_id'  => $accountId,
            'type'        => $type,
            'amount'      => $amount,
            'description' => $description,
            'entry_date'  => $entryDate,
            'metadata'    => $metadata,
        ]);
    }
}