<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Employee extends Model

{
    use SoftDeletes;

    protected $fillable = [
        'identification',
        'first_name',
        'last_name',
        'email',
        'phone',
        'position',
        'salary',
        'hired_at',
        'created_by',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'hired_at' => 'date',
    ];

    // Mutators para guardar en mayúsculas
    public function setFirstNameAttribute($value)
    {
        $this->attributes['first_name'] = strtoupper(trim($value));
    }

    public function setLastNameAttribute($value)
    {
        $this->attributes['last_name'] = strtoupper(trim($value));
    }

    public function setPositionAttribute($value)
    {
        $this->attributes['position'] = strtoupper(trim($value));
    }

    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower(trim($value));
    }

    public function setIdentificationAttribute($value)
    {
        $this->attributes['identification'] = trim($value);
    }

    public function setPhoneAttribute($value)
    {
        $this->attributes['phone'] = trim($value);
    }

    // Relación con el usuario que creó el empleado
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class , 'created_by');
    }
}
