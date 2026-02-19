<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeePayment extends Model
{
    protected $fillable = [
        'employee_name',
        'amount',
        'payment_date',
        'concept',
    ];

    protected $casts = [
        'payment_date' => 'date:Y-m-d',
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

    public function transaction()
    {
        return $this->morphOne(Transaction::class, 'transactionable');
    }
}
