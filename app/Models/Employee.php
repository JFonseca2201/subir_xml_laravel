<?php

namespace App\Models;

use App\Models\Config\Sucursale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'surname',
        'full_name',
        'dni',
        'phone',
        'email',
        'birth_date',
        'address',
        'gender',
        'position',
        'salary',
        'hire_date',
        'account_number',
        'bank_name',
        'status',
        'user_id',
        'sucursale_id',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'hire_date' => 'date',
        'salary' => 'decimal:2',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
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

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursale::class, 'sucursale_id');
    }

    public function getFullNameAttribute()
    {
        return trim($this->name . ' ' . $this->surname);
    }

    public function getAgeAttribute()
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }

    public function getYearsOfServiceAttribute()
    {
        return $this->hire_date ? $this->hire_date->diffInYears(Carbon::now()) : null;
    }

    public function getGenderNameAttribute()
    {
        switch ($this->gender) {
            case 1:
                return 'Masculino';
            case 2:
                return 'Femenino';
            case 3:
                return 'Otro';
            default:
                return null;
        }
    }

    public function getStatusNameAttribute()
    {
        switch ($this->status) {
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
        return $query->where('status', 'active');
    }

    public function scopeBySucursal($query, $sucursaleId)
    {
        return $query->where('sucursale_id', $sucursaleId);
    }

    /**
     * Encriptar el número de cuenta antes de guardarlo
     */
    public function setAccountNumberAttribute($value)
    {
        if ($value) {
            $this->attributes['account_number'] = Crypt::encryptString($value);
        }
    }

    /**
     * Desencriptar el número de cuenta al obtenerlo
     */
    public function getAccountNumberAttribute($value)
    {
        if ($value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                // Si no se puede desencriptar, retornar el valor original
                return $value;
            }
        }
        return null;
    }

    /**
     * Ocultar el número de cuenta en las serializaciones JSON
     */
    public function toArray()
    {
        $array = parent::toArray();

        // Ocultar el número de cuenta completo, mostrar solo últimos 4 dígitos
        if (isset($array['account_number']) && $array['account_number']) {
            $accountNumber = $this->account_number;
            $array['account_number'] = '****-****-' . substr($accountNumber, -4);
        }

        // Agregar nombres legibles para gender y status
        $array['gender_name'] = $this->gender_name;
        $array['status_name'] = $this->status_name;

        return $array;
    }

    /**
     * Obtener el número de cuenta completo (solo para uso interno)
     */
    public function getFullAccountNumber()
    {
        return $this->account_number;
    }
}
