<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\RecordsFinancialMovements;

class PaymentDistribution extends Model
{
    use RecordsFinancialMovements;

    protected $fillable = [
        'finance_record_id',
        'account_id',
        'amount',
        'payment_method'
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

    public function financeRecord()
    {
        return $this->belongsTo(FinanceRecord::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}