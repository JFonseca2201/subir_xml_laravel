<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'suppliers';

    protected $fillable = ['tax_id', 'ruc', 'supplier_id', 'name', 'address'];

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

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'tax_id');
    }

    public function getFormattedRucAttribute()
    {
        if (!$this->ruc) {
            return null;
        }
        
        // Formatear RUC ecuatoriano (13 dígitos)
        $ruc = $this->ruc;
        if (strlen($ruc) === 13) {
            return substr($ruc, 0, 2) . '-' . substr($ruc, 2, 3) . '-' . substr($ruc, 5, 3) . '-' . substr($ruc, 8);
        }
        
        return $ruc;
    }
}
