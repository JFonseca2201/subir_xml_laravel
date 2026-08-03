<?php

namespace App\Models\Sales;

use App\Models\Product\Product;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepuestosReposicion extends Model
{
    use HasFactory;

    protected $table = 'repuestos_reposicion';

    protected $fillable = [
        'product_id',
        'sku',
        'description',
        'quantity',
        'purchase_price',
        'supplier_id',
        'status',
        'sale_id'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'purchase_price' => 'decimal:2',
    ];

    /**
     * El repuesto a reponer pertenece a un producto.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * El repuesto puede tener un distribuidor sugerido.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Proviene de una venta.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
