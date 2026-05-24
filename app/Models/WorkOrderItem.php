<?php

namespace App\Models;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrderItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'work_order_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'subtotal',
        'type',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Un item pertenece a una orden de trabajo.
     */
    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Un item puede estar asociado a un producto.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
