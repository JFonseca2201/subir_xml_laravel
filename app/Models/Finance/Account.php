<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = ['code', 'name', 'type', 'bank_name', 'initial_balance', 'saldo_actual', 'is_active', 'is_system'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'initial_balance' => 'decimal:2',
        'saldo_actual' => 'decimal:2',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = now()->setTimezone('America/Guayaquil');
        });

        static::updating(function ($model) {
            $model->updated_at = now()->setTimezone('America/Guayaquil');
        });
    }

    protected $appends = ['current_balance'];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function financeRecords()
    {
        return $this->hasMany(FinanceRecord::class, 'account_id');
    }

    public function outgoingTransfers()
    {
        return $this->hasMany(Transfer::class, 'from_account_id');
    }

    public function incomingTransfers()
    {
        return $this->hasMany(Transfer::class, 'to_account_id');
    }

    public function getCurrentBalanceAttribute()
    {
        return (float) ($this->attributes['saldo_actual'] ?? $this->saldo_actual ?? 0);
    }

    /**
     * Update account balance based on transaction
     */
    public function updateBalance($amount, $type): void
    {
        $amount = (float) $amount;
        if ($type === 0 || $type === '0' || $type === 'income' || $type === FinanceRecord::TYPE_INCOME) {
            $this->increment('saldo_actual', $amount);
        } else {
            $this->decrement('saldo_actual', $amount);
        }
    }
}
