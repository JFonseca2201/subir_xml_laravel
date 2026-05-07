<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeAdvance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employee_advances';

    protected $fillable = [
        'employee_id',
        'account_id',
        'amount',
        'description',
        'advance_date',
        'payment_method',
        'reason',
        'type',
        'created_by',
        'is_deducted'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'advance_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_deducted' => 'boolean',
    ];

    protected $dates = [
        'advance_date'
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
        return $query->whereBetween('advance_date', [$startDate, $endDate]);
    }

    public function scopePending($query)
    {
        return $query->where('is_deducted', false);
    }
}
