<?php

namespace App\Models\Vehicles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'client_id',
        'license_plate',
        'brand',
        'model',
        'year',
        'color',
        'vehicle_type',
        'usage_type',
        'description',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
        'year' => 'integer',
    ];

    protected $appends = [
        'brand_name',
        'formatted_plate',
    ];

    /**
     * Limpia la placa removiendo guiones, espacios y caracteres especiales,
     * convirtiéndola a mayúsculas limpias para guardarla en la base de datos (ej. PBA854).
     */
    public static function cleanPlate(?string $plate): ?string
    {
        if (empty($plate)) {
            return null;
        }

        $trimmed = strtoupper(trim($plate));

        // Placas genéricas / especiales
        if (preg_match('/^(SIN-PLACA|SIN PLACA|S\/P|S\/P-[A-Z0-9]+|SP-\d{3,4})$/i', $trimmed)) {
            return 'SIN-PLACA';
        }

        // Remover guiones, espacios y cualquier carácter no alfanumérico
        return preg_replace('/[^A-Z0-9]/', '', $trimmed);
    }

    /**
     * Formatea una placa limpia para presentación visual con guión (ej. PBA854 -> PBA-854).
     */
    public static function formatPlate(?string $plate): ?string
    {
        if (empty($plate)) {
            return null;
        }

        $clean = self::cleanPlate($plate);

        if ($clean === 'SIN-PLACA' || str_contains($clean, 'S/P')) {
            return $clean;
        }

        // Formato estándar (3 letras + 3 o 4 dígitos) ej: PBA-854, ABC-1234
        if (preg_match('/^([A-Z]{3})(\d{3,4})$/', $clean, $matches)) {
            return "{$matches[1]}-{$matches[2]}";
        }

        // Formato moto (1 letra + 3 dígitos + 1 letra) ej: A-123B
        if (preg_match('/^([A-Z])(\d{3}[A-Z])$/', $clean, $matches)) {
            return "{$matches[1]}-{$matches[2]}";
        }

        // Formato antiguo (4 dígitos + 3 letras) ej: 1234-ABC
        if (preg_match('/^(\d{4})([A-Z]{3})$/', $clean, $matches)) {
            return "{$matches[1]}-{$matches[2]}";
        }

        return $clean;
    }

    /**
     * Mutator para license_plate:
     * Asegura que siempre se guarde estrictamente limpia (sin guiones) y en mayúsculas.
     */
    public function setLicensePlateAttribute($value)
    {
        $this->attributes['license_plate'] = self::cleanPlate($value);
    }

    /**
     * Accessor para formatted_plate:
     * Permite acceder al formato con guión visual (ej. $vehicle->formatted_plate -> PBA-854).
     */
    public function getFormattedPlateAttribute(): ?string
    {
        return self::formatPlate($this->attributes['license_plate'] ?? null);
    }

    /**
     * Scope para buscar por placa independientemente de si el término o la base de datos
     * tienen o no guiones.
     */
    public function scopeWherePlateLike($query, $search)
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$search));

        return $query->where(function ($q) use ($search, $clean) {
            $q->where('license_plate', 'like', "%{$search}%");
            if (!empty($clean)) {
                $q->orWhere('license_plate', 'like', "%{$clean}%")
                  ->orWhereRaw("REPLACE(license_plate, '-', '') like ?", ["%{$clean}%"]);
            }
        });
    }

    public function getBrandNameAttribute()
    {
        if (empty($this->brand)) {
            return null;
        }
        $brands = config('vehicle_brands', []);
        $key = is_numeric($this->brand) ? (int)$this->brand : $this->brand;
        return $brands[$key] ?? $this->brand;
    }

    // Relación para saber quién lo ingresó
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class , 'user_id');
    }

    public function client()
    {
        return $this->belongsTo(\App\Models\Client\Client::class, 'client_id');
    }

    public function clients()
    {
        return $this->belongsToMany(\App\Models\Client\Client::class, 'work_orders', 'vehicle_id', 'client_id')->distinct();
    }
}
