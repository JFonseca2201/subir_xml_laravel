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
        'transaction_date' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

   public function transactionable()
    {
        return $this->morphOne(Transaction::class, 'transactionable');
    }

}