<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountTransaction extends Model
{
    protected $fillable = [
        'account_id',
        'type',
        'category',
        'amount',
        'description',
        'reference_id',
        'reference_type',
        'transfer_group_id',
        'transaction_date',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function getFormattedAmountAttribute(): string
    {
        $prefix = $this->type === 'expense' ? '-' : '+';
        return $prefix . '$' . number_format($this->amount, 2);
    }

    public function getTransactionDateFormattedAttribute(): string
    {
        return $this->transaction_date->format('d/m/Y');
    }
}
