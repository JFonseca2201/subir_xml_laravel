<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = ['code', 'name', 'type', 'bank_name', 'initial_balance', 'is_active', 'is_system'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
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

    // 🔥 Saldo dinámico con FinanceRecord
    public function getCurrentBalanceAttribute()
    {
        // Usar FinanceRecord para el cálculo de saldo
        $income = $this->financeRecords()->where('type', 0)->sum('amount'); // 0 = Income

        $expense = $this->financeRecords()->where('type', 1)->sum('amount'); // 1 = Expense

        return $this->initial_balance + $income - $expense;
    }

    /**
     * Update account balance based on transaction
     */
    public function updateBalance($amount, $type): void
    {
        $amount = (float) $amount;
        $type = (int) $type;

        if ($type === 0) {
            // Income: add to balance
            $this->saldo_actual += $amount;
        } else {
            // Expense: subtract from balance
            $this->saldo_actual -= $amount;
        }

        $this->save();
    }
}
