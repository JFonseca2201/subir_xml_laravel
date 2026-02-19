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
}
