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

    protected $appends = ['current_balance', 'saldo_actual'];

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

        return (float) (($this->initial_balance ?? 0) + $income - $expense);
    }

    public function getSaldoActualAttribute()
    {
        return $this->getCurrentBalanceAttribute();
    }

    public function syncSaldoActual()
    {
        $realBalance = $this->getCurrentBalanceAttribute();
        \Illuminate\Support\Facades\DB::table('accounts')
            ->where('id', $this->id)
            ->update(['saldo_actual' => $realBalance]);
        return $realBalance;
    }

    public static function recalculateAllSaldos()
    {
        foreach (static::all() as $account) {
            $account->syncSaldoActual();
        }
    }

    /**
     * Update account balance based on transaction
     */
    public function updateBalance($amount, $type): void
    {
        $this->syncSaldoActual();
    }
}
