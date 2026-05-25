<?php

namespace App\Models\Employee;

use App\Models\Finance\Account;

use App\Models\User;

use App\Traits\RecordsFinancialMovements;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeePayment extends Model
{
    use HasFactory, SoftDeletes;
    use RecordsFinancialMovements;

    protected $table = 'employee_payments';

    protected $fillable = [
        'employee_id',
        'account_id',
        'amount',
        'description',
        'payment_date',
        'payment_method',
        'reference',
        'type',
        'created_by'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $dates = [
        'payment_date'
    ];

    // Relaciones
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scope para filtrar por tipo
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }
}