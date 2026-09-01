<?php

namespace App\Models\Vehicles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', // <--- Añadido
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
    ];

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

