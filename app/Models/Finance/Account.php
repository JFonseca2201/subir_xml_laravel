<?php

namespace App\Models\Finance;

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

    // 🔥 Saldo dinámico con PaymentDistribution
    public function getCurrentBalanceAttribute()
    {
        // Usar PaymentDistribution para el cálculo de saldo
        $income = PaymentDistribution::where('account_id', $this->id)
            ->whereHas('financeRecord', function ($query) {
                $query->where('type', 0); // 0 = Income
            })
            ->sum('amount');

        $expense = PaymentDistribution::where('account_id', $this->id)
            ->whereHas('financeRecord', function ($query) {
                $query->where('type', 1); // 1 = Expense
            })
            ->sum('amount');

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
