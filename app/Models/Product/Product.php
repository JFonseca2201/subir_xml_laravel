<?php

namespace App\Models\Product;

use App\Models\Config\ProductCategorie;
use App\Models\Config\Unit;
use App\Models\Config\Warehouse;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * The attributes that are mass assignable.
 *
 * @var array<int, string>
 */
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'description', //name
        'sku',
        'imagen',
        'code_aux',
        'uses',
        'product_categorie_id',
        'warehouse_id',
        'unit_id',
        'supplier_id',
        'price',
        'price_sale',
        'purchase_price',
        'wholesale_price',
        'tax_rate',
        'discount',
        'max_discount',
        'discount_percentage',
        'brand',
        'stock',
        'item_type',
        'min_stock',
        'max_stock',
        'is_taxable',
        'is_active',
        'is_gift',
        'notes',
        'state',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'price' => 'decimal:2',
        'price_sale' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'discount' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'min_stock' => 'decimal:2',
        'max_stock' => 'decimal:2',
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
        'is_gift' => 'boolean',
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

    public function getProductImagenAttribute()
    {
        $link = null;
        if ($this->imagen) {
            $link = env('APP_URL') . 'storage/' . $this->imagen;
        }

        return $link;
    }

    public function scopeFilterAdvance($query, $search, $categorie_id, $warehouse_id, $unit_id, $sucursale_id, $disponibilidad, $is_gift)
    {
        if ($search) {
            $query->where(DB::raw("products.description || '' || products.sku"), 'ilike', '%' . $search . '%');
        }
        if ($categorie_id) {
            $query->where('product_categorie_id', $categorie_id);
        }
        if ($disponibilidad) {
            $query->where('disponibilidad', $disponibilidad);
        }
        if ($is_gift) {
            $query->where('is_gift', $is_gift);
        }
        if ($warehouse_id) {
            $query->whereHas('warehouses', function ($warehouse) use ($warehouse_id) {
                $warehouse->where('warehouse_id', $warehouse_id);
            });
        }
        if ($unit_id) {
            $query->whereHas('units', function ($unit) use ($unit_id) {
                $unit->where('unit_id', $unit_id);
            });
        }/* 
        if ($sucursale_id) {
            $query->whereHas('wallets', function ($wallet) use ($sucursale_id) {
                $wallet->where('sucursale_id', $sucursale_id);
            });
        } */

        return $query;
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

    public function setSkuAttribute($value)
    {
        $this->attributes['sku'] = strtoupper(trim($value));
    }

    public function setBrandAttribute($value)
    {
        $this->attributes['brand'] = strtoupper(trim($value));
    }

    public function categorie()
    {
        return $this->belongsTo(ProductCategorie::class, 'product_categorie_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
