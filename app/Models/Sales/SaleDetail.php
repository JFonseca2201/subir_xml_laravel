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
        'tax_rate',
        'tax_value',
        'total'
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'discount'  => 'decimal:2',
        'tax_rate'  => 'decimal:2',
        'tax_value' => 'decimal:2',
        'total'     => 'decimal:2',
    ];

    /**
     * El detalle pertenece a una venta maestra.
     */
    public function sale(): BelongsTo
    {
        // Apuntamos al modelo maestro hermano en la misma carpeta sales
        return $this->belongsTo(Sale::class , 'sale_id');
    }

    /**
     * El detalle puede estar enlazado a un producto del inventario general.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}