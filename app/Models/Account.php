<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Transaction;

class Account extends Model
{
    protected $fillable = ['code', 'name', 'type', 'bank_name', 'initial_balance', 'is_active', 'is_system'];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
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

    public function outgoingTransfers()
    {
        return $this->hasMany(Transfer::class, 'from_account_id');
    }

    public function incomingTransfers()
    {
        return $this->hasMany(Transfer::class, 'to_account_id');
    }

    // Saldo dinámico
    public function getCurrentBalanceAttribute()
    {
        $income = $this->transactions()->where('type', Transaction::TYPE_INCOME)->sum('amount');
        $expense = $this->transactions()->where('type', Transaction::TYPE_EXPENSE)->sum('amount');
        
        return $this->initial_balance + $income - $expense;
    }
}
