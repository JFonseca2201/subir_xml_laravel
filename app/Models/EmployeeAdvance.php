<?php

namespace App\Models;

use App\Models\Config\Sucursale;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeAdvance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'user_id',
        'amount',
        'advance_date',
        'concept',
        'state',
        'employee_payment_id',
        'sucursale_id',
    ];

    protected $casts = [
        'advance_date' => 'date',
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

    public function employeePayment()
    {
        return $this->belongsTo(EmployeePayment::class, 'employee_payment_id');
    }

    public function getStateNameAttribute()
    {
        switch ($this->state) {
            case 1:
                return 'Pendiente';
            case 2:
                return 'Descontado';
            case 3:
                return 'Anulado';
            default:
                return null;
        }
    }

    public function getFormattedAmountAttribute()
    {
        return '$' . number_format($this->amount, 2);
    }

    public function getAdvanceDateFormattedAttribute()
    {
        return $this->advance_date ? $this->advance_date->format('d/m/Y') : null;
    }

    public function scopePending($query)
    {
        return $query->where('state', 1);
    }

    public function scopeDiscounted($query)
    {
        return $query->where('state', 2);
    }

    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeBySucursal($query, $sucursaleId)
    {
        return $query->where('sucursale_id', $sucursaleId);
    }
}
