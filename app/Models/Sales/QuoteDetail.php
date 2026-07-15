<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuoteDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_id',
        'product_id',
        'description',
        'quantity',
        'price',
        'discount',
        'total',
    ];

    protected $casts = [
        'price'    => 'decimal:2',
        'discount' => 'decimal:2',
        'total'    => 'decimal:2',
    ];

    /**
     * Relación: cabecera de la cotización.
     */
    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }
    /**
     * Relación: producto asociado (opcional).
     */
    public function product()
    {
        return $this->belongsTo(\App\Models\Product\Product::class);
    }
}
