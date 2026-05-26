<?php

namespace App\Models\Supplier;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PedidoDistribuidor extends Model
{
    protected $table = 'pedidos_distribuidor';

    protected $fillable = [
        'distribuidor_id',
        'usuario_id',
        'estado',
        'total'
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = now()->setTimezone('America/Guayaquil');
            $model->updated_at = now()->setTimezone('America/Guayaquil');
        });

        static::updating(function ($model) {
            $model->updated_at = now()->setTimezone('America/Guayaquil');
        });
    }

    public function distribuidor()
    {
        return $this->belongsTo(Supplier::class, 'distribuidor_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function detalles()
    {
        return $this->hasMany(PedidoDistribuidorDetalle::class, 'pedido_id');
    }
}
