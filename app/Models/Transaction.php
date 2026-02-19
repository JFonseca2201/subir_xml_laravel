<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'account_id',
        'type',
        'amount',
        'concept',
        'description',
        'transaction_date',
    ];

    protected $casts = [
        'transaction_date' => 'datetime:Y-m-d H:i:s',
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
        return $this->belongsTo(Account::class);
    }

    public function transactionable()
    {
        return $this->morphOne(Transaction::class, 'transactionable');
    }
}
