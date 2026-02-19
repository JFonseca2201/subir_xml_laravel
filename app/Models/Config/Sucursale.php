<?php

namespace App\Models\Config;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sucursale extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'ruc',
        'trade_name',
        'secuencial_factura',
        'serie_factura',
        'establecimiento',
        'punto_emision',
        'ambiente',
        'tipo_emision',
        'firma_electronica',
        'password_firma',
        'logo',
        'obligado_contabilidad',
        'contribuyente_especial',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = now()->setTimezone('America/Guayaquil');
        });

        static::updating(function ($model) {
            $model->updated_at = now()->setTimezone('America/Guayaquil');
        });
    }
}
