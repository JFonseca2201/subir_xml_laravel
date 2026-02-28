<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'description', //name
        'code',
        'imagen',
        'code_aux',
        'uses',
        'categorie_id',
        'warehose_id',
        'unit_id',
        'price',
        'purchase_price',
        'wholesale_price',
        'tax_rate',
        'discount_percentage',
        'barcode',
        'sku',
        'brand',
        'stock',
        'item_type',
        'min_stock',
        'max_stock',
        'is_taxable',
        'is_active',
        'notes',
        'state',

    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'min_stock' => 'decimal:2',
        'max_stock' => 'decimal:2',
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
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

    public function setDescriptionAttribute($value)
    {
        $this->attributes['description'] = strtoupper(trim($value));
    }

    public function setCodeAttribute($value)
    {
        $this->attributes['code'] = trim($value);
    }

    public function setCodeAuxAttribute($value)
    {
        $this->attributes['code_aux'] = strtoupper(trim($value));
    }

    public function setBarcodeAttribute($value)
    {
        $this->attributes['barcode'] = trim($value);
    }

    public function setSkuAttribute($value)
    {
        $this->attributes['sku'] = strtoupper(trim($value));
    }

    public function setBrandAttribute($value)
    {
        $this->attributes['brand'] = strtoupper(trim($value));
    }

    public function setUnitOfMeasureAttribute($value)
    {
        $this->attributes['unit'] = strtoupper(trim($value));
    }

    public function categorie()
    {
        return $this->belongsTo(\App\Models\Config\ProductCategorie::class, 'categorie_id');
    }

    public function unit()
    {
        return $this->belongsTo(\App\Models\Config\Unit::class, 'unit_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(\App\Models\Config\Warehouse::class, 'warehose_id');
    }
}
