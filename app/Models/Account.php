<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'name',
        'type',
        'bank_name',
        'initial_balance',
        'is_active',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function outgoingTransfers()
    {
        return $this->hasMany(Transfer::class, 'from_account_id');
    }

    public function incomingTransfers()
    {
        return $this->hasMany(Transfer::class, 'to_account_id');
    }

    // 🔥 Saldo dinámico
    public function getCurrentBalanceAttribute()
    {
        $income = $this->transactions()
            ->where('type', 'income')
            ->sum('amount');

        $expense = $this->transactions()
            ->where('type', 'expense')
            ->sum('amount');

        return $this->initial_balance + $income - $expense;
    }
}