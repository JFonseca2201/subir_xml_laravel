<?php

namespace App\Traits;

use App\Models\Finance\FinancialMovement;
use Illuminate\Database\Eloquent\Model;

trait RecordsFinancialMovements
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function financialMovement()
    {
        /** @var Model $this */
        return $this->morphOne(FinancialMovement::class, 'movable');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function financialMovements()
    {
        /** @var Model $this */
        return $this->morphMany(FinancialMovement::class, 'movable');
    }

    /**
     * Método para registrar un nuevo movimiento financiero.
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

    /**
     * Método para actualizar el movimiento financiero existente en lugar de duplicarlo.
     * Busca el registro original por relación polimórfica (movable_type y movable_id).
     * Si no existe, lo crea. Si existen duplicados históricos previos, los limpia para garantizar unicidad.
     */
    public function updateMovement($accountId, $type, $amount, $description, $entryDate, $metadata = [])
    {
        $movements = FinancialMovement::where('movable_type', get_class($this))
            ->where('movable_id', $this->id)
            ->orderBy('id', 'asc')
            ->get();

        if ($movements->isNotEmpty()) {
            $movement = $movements->first();

            $existingMetadata = is_array($movement->metadata) ? $movement->metadata : [];
            $mergedMetadata = !empty($metadata) ? array_merge($existingMetadata, $metadata) : $existingMetadata;

            $movement->update([
                'account_id'  => $accountId,
                'type'        => $type,
                'amount'      => $amount,
                'description' => $description,
                'entry_date'  => $entryDate,
                'metadata'    => $mergedMetadata,
            ]);

            // Limpiar duplicados huérfanos generados previamente por bugs anteriores
            if ($movements->count() > 1) {
                FinancialMovement::where('movable_type', get_class($this))
                    ->where('movable_id', $this->id)
                    ->where('id', '!=', $movement->id)
                    ->delete();
            }

            return $movement;
        }

        return $this->registerMovement($accountId, $type, $amount, $description, $entryDate, $metadata);
    }

    /**
     * Alias de updateMovement para mayor compatibilidad
     */
    public function updateOrCreateMovement($accountId, $type, $amount, $description, $entryDate, $metadata = [])
    {
        return $this->updateMovement($accountId, $type, $amount, $description, $entryDate, $metadata);
    }
}
