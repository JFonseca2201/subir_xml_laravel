<?php

namespace App\Models\Sales;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class SaleDetail extends Model
{
    use HasFactory;

    protected $table = 'sale_details';

    protected $fillable = [
        'sale_id', 
        'product_id', 
        'description', 
        'quantity', 
        'price', 
        'discount', 
        'total'
    ];

    /**
     * El detalle pertenece a una venta maestra.
     */
    public function sale(): BelongsTo
    {
        // Apuntamos al modelo maestro hermano en la misma carpeta sales
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    /**
     * El detalle puede estar enlazado a un producto del inventario general.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}