<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'bank_name',
        'initial_balance',
        'current_balance',
        'state'
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'initial_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'state' => 'integer',
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
        return $this->hasMany(AccountTransaction::class);
    }

    public function outgoingTransfers()
    {
        return $this->hasMany(\App\Models\Transfer::class, 'from_account_id');
    }

    public function incomingTransfers()
    {
        return $this->hasMany(\App\Models\Transfer::class, 'to_account_id');
    }

    // Saldo dinámico con manejo de errores
    public function getCurrentBalanceAttribute()
    {
        try {
            $income = $this->transactions()->where('type', 'income')->sum('amount');
            $expense = $this->transactions()->where('type', 'expense')->sum('amount');

            return $this->initial_balance + $income - $expense;
        } catch (\Exception $e) {
            // Si hay error en la relación, retornar solo el balance inicial
            return $this->initial_balance ?? 0;
        }
    }

    // Scope para cuentas activas
    public function scopeActive($query)
    {
        return $query->where('state', 1);
    }

    // Scope para cuentas de tipo específico
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
