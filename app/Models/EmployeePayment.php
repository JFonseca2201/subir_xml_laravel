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
        'payment_date' => 'date',
    ];

    public function transaction()
    {
        return $this->morphOne(Transaction::class, 'transactionable');
    }
}