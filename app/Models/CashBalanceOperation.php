<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashBalanceOperation extends Model
{
    protected $fillable = [
        'operation_type',
        'operation_date',
        'account_id',
        'user_id',
        'system_balance',
        'expected_balance',
        'actual_balance',
        'difference',
        'cash_denomination_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'operation_date' => 'date',
        'system_balance' => 'decimal:2',
        'expected_balance' => 'decimal:2',
        'actual_balance' => 'decimal:2',
        'difference' => 'decimal:2',
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

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cashDenomination()
    {
        return $this->belongsTo(CashDenomination::class, 'cash_denomination_id');
    }

    public function getOperationTypeDescriptionAttribute()
    {
        $types = [
            'opening' => 'Apertura',
            'closing' => 'Cierre'
        ];

        return $types[$this->operation_type] ?? 'Desconocido';
    }

    public function getStatusDescriptionAttribute()
    {
        $statuses = [
            'pending' => 'Pendiente',
            'completed' => 'Completado',
            'reconciled' => 'Conciliado'
        ];

        return $statuses[$this->status] ?? 'Desconocido';
    }

    public function getFormattedSystemBalanceAttribute()
    {
        return '$' . number_format($this->system_balance, 2);
    }

    public function getFormattedExpectedBalanceAttribute()
    {
        return '$' . number_format($this->expected_balance, 2);
    }

    public function getFormattedActualBalanceAttribute()
    {
        return '$' . number_format($this->actual_balance, 2);
    }

    public function getFormattedDifferenceAttribute()
    {
        $diff = abs($this->difference);
        $prefix = $this->difference >= 0 ? '+' : '-';
        return $prefix . ' $' . number_format($diff, 2);
    }

    public function getBalanceStatusAttribute()
    {
        if (abs($this->difference) < 0.01) {
            return 'balanced';
        } elseif ($this->difference > 0) {
            return 'surplus';
        } else {
            return 'shortage';
        }
    }

    public function getBalanceStatusDescriptionAttribute()
    {
        $statuses = [
            'balanced' => 'Balanceado',
            'surplus' => 'Sobrante',
            'shortage' => 'Faltante'
        ];

        return $statuses[$this->balance_status] ?? 'Desconocido';
    }
}
