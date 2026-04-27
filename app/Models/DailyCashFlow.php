<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyCashFlow extends Model
{
    protected $fillable = [
        'flow_type',
        'flow_date',
        'order_number',
        'order_id',
        'total_amount',
        'payment_status',
        'description',
        'account_type',
        'account_id',
        'payment_method',
        'user_id',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'flow_date' => 'date',
        'total_amount' => 'decimal:2',
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

    public function getFormattedAmountAttribute()
    {
        return '$' . number_format($this->total_amount, 2);
    }

    public function getFlowDateFormattedAttribute()
    {
        return $this->flow_date ? $this->flow_date->format('d/m/Y') : null;
    }

    public function getAccountTypeDescriptionAttribute()
    {
        $types = [
            1 => 'Caja Chica',
            2 => 'Caja',
            3 => 'Bancos'
        ];

        return $types[$this->account_type] ?? 'Desconocido';
    }

    public function getSourceTypeDescriptionAttribute()
    {
        $types = [
            'sale' => 'Venta',
            'purchase' => 'Compra',
            'other' => 'Otro'
        ];

        return $types[$this->source_type] ?? 'Desconocido';
    }

    public function getPaymentStatusDescriptionAttribute()
    {
        $statuses = [
            'complete' => 'Completo',
            'partial' => 'Parcial',
            'pending' => 'Pendiente'
        ];

        return $statuses[$this->payment_status] ?? 'Desconocido';
    }

    public function getPaymentMethodDescriptionAttribute()
    {
        $methods = [
            'cash' => 'Efectivo',
            'transfer' => 'Transferencia'
        ];

        return $methods[$this->payment_method] ?? 'Desconocido';
    }
}
