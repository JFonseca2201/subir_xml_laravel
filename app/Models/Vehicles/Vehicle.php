<?php

namespace App\Models\Vehicles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',      // <--- Añadido
        'license_plate',
        'brand',
        'model',
        'year',
        'color',
        'vehicle_type',
        'description',
        'status',       // <--- Añadido
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
        'year' => 'integer',
    ];

    // Relación para saber quién lo ingresó
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
