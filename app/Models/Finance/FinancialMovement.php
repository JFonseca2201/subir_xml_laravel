<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialMovement extends Model

{
    protected $fillable = [
        'movable_id',
        'movable_type',
        'type',
        'amount',
        'description',
        'entry_date',
        'account_id',
        'metadata'
    ];

    protected $casts = [
        'entry_date' => 'date',
        'metadata' => 'array',
        'amount' => 'decimal:2'
    ];

    // Relación para saber a qué registro pertenece (Pago, Aporte, etc.)
    public function movable(): MorphTo
    {
        return $this->morphTo();
    }

    // Relación con la cuenta afectada
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}