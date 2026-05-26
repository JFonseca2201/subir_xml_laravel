<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Product\Product;

class ProductReturnDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_return_id',
        'product_id',
        'description',
        'quantity',
        'price',
        'total',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function productReturn(): BelongsTo
    {
        return $this->belongsTo(ProductReturn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
