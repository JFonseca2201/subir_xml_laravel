<?php

namespace App\Models;

use App\Models\Config\Sucursale;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeePayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'user_id',
        'amount',
        'payment_date',
        'concept',
        'payment_type',
        'state',
        'sucursale_id',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
        'amount' => 'decimal:2',
        'state' => 'integer',
    ];

    public function setCreatedAtAttribute($value)
    {
        date_default_timezone_set('America/Guayaquil');
        $this->attributes['created_at'] = Carbon::now();
    }

    public function setUpdatedAtAttribute($value)
    {
        date_default_timezone_set('America/Guayaquil');
        $this->attributes['updated_at'] = Carbon::now();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursale::class, 'sucursale_id');
    }

    public function advances()
    {
        return $this->hasMany(EmployeeAdvance::class, 'employee_payment_id');
    }

    public function transaction()
    {
        return $this->morphOne(Transaction::class, 'transactionable');
    }

    public function getFormattedAmountAttribute()
    {
        return '$' . number_format($this->amount, 2);
    }

    public function getStateNameAttribute()
    {
        switch ($this->state) {
            case 1:
                return 'Activo';
            case 2:
                return 'Inactivo';
            default:
                return null;
        }
    }

    public function scopeActive($query)
    {
        return $query->where('state', 1);
    }

    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeBySucursal($query, $sucursaleId)
    {
        return $query->where('sucursale_id', $sucursaleId);
    }

    public function getPaymentDateFormattedAttribute()
    {
        return $this->payment_date ? $this->payment_date->format('d/m/Y') : null;
    }

    public function getTotalAdvancesAttribute()
    {
        return $this->advances()->sum('amount');
    }

    public function getNetAmountAttribute()
    {
        return $this->amount - $this->total_advances;
    }

    public function getFormattedNetAmountAttribute()
    {
        return '$' . number_format($this->net_amount, 2);
    }

    public function getFormattedTotalAdvancesAttribute()
    {
        return '$' . number_format($this->total_advances, 2);
    }

    public function getPaymentTypeNameAttribute()
    {
        switch ($this->payment_type) {
            case 'cash':
                return 'Efectivo';
            case 'transfer':
                return 'Transferencia';
            case 'check':
                return 'Cheque';
            default:
                return ucfirst($this->payment_type);
        }
    }
}
