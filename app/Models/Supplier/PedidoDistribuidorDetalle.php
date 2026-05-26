<?php

namespace App\Models\Supplier;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Model;

class PedidoDistribuidorDetalle extends Model
{
    protected $table = 'pedido_distribuidor_detalles';

    protected $fillable = [
        'pedido_id',
        'producto_id',
        'description',
        'cantidad',
        'precio_compra_estimado'
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_compra_estimado' => 'decimal:2',
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

    public function pedido()
    {
        return $this->belongsTo(PedidoDistribuidor::class, 'pedido_id');
    }

    public function producto()
    {
        return $this->belongsTo(Product::class, 'producto_id');
    }
}
