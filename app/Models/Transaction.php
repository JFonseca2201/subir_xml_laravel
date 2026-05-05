<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Account;
use App\Models\User;
use App\Models\Partner;
use App\Models\Employee;
use App\Models\WorkOrder;

class Transaction extends Model
{
    // Constantes para tipos de transacción
    const TYPE_INCOME = 1;
    const TYPE_EXPENSE = 2;
    const TYPE_TRANSFER = 3;

    protected $fillable = [
        'account_id',
        'type', 
        'amount',
        'concept',
        'description',
        'transactionable_type',
        'transactionable_id',
        'transaction_date'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
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

    // Relaciones
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    // Accessors
    public function getFormattedAmountAttribute()
    {
        return '$' . number_format($this->amount, 2);
    }

    public function getTypeLabelAttribute()
    {
        return match($this->type) {
            self::TYPE_INCOME => 'Ingreso',
            self::TYPE_EXPENSE => 'Egreso',
            self::TYPE_TRANSFER => 'Transferencia',
            default => 'Desconocido'
        };
    }

    // Scopes
    public function scopeIncome($query)
    {
        return $query->where('type', self::TYPE_INCOME);
    }

    public function scopeExpense($query)
    {
        return $query->where('type', self::TYPE_EXPENSE);
    }

    public function scopeTransfer($query)
    {
        return $query->where('type', self::TYPE_TRANSFER);
    }

    public function scopeByAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeByTransferGroup($query, $transferGroupId)
    {
        return $query->where('transfer_group_id', $transferGroupId);
    }
}
