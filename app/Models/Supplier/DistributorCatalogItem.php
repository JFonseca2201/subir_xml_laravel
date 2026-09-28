<?php

namespace App\Models\Supplier;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributorCatalogItem extends Model
{
    protected $table = 'distributor_catalog_items';

    protected $fillable = [
        'supplier_id',
        'supplier_name',
        'code',
        'description',
        'reference',
        'price',
        'category_code',
        'category_name',
        'stock_status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Relación con el distribuidor / proveedor.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Scope para filtrar por distribuidor.
     */
    public function scopeBySupplier($query, $supplierId)
    {
        if ($supplierId && $supplierId !== 'all') {
            return $query->where('supplier_id', $supplierId);
        }
        return $query;
    }

    /**
     * Scope para filtrar por categoría.
     */
    public function scopeByCategory($query, $categoryName)
    {
        if ($categoryName && $categoryName !== 'ALL' && $categoryName !== 'all') {
            return $query->where('category_name', $categoryName);
        }
        return $query;
    }

    /**
     * Scope para búsqueda general (código, descripción, referencia).
     */
    public function scopeSearch($query, $search)
    {
        if (!empty($search)) {
            $search = trim($search);
            return $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('category_name', 'like', "%{$search}%");
            });
        }
        return $query;
    }
}
