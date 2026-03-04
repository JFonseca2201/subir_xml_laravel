<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => strtoupper(trim($this->description)),
            'sku' => strtoupper(trim($this->sku)),
            'imagen' => $this->imagen,
            'code_aux' => strtoupper(trim($this->code_aux)),
            'uses' => $this->uses,
            'product_categorie_id' => $this->product_categorie_id,
            'warehouse_id' => $this->warehouse_id,
            'unit_id' => $this->unit_id,
            'supplier_id' => $this->supplier_id,
            'categorie' => $this->categorie ? [
                'id' => $this->categorie->id,
                'title' => $this->categorie->title,
            ] : null,
            'warehouse' => $this->warehouse ? [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ] : null,
            'unit' => $this->unit ? [
                'id' => $this->unit->id,
                'name' => strtoupper(trim($this->unit->name)),
            ] : null,
            'supplier' => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null,
            'price' => (float) $this->price,
            'price_sale' => (float) $this->price_sale,
            'purchase_price' => (float) $this->purchase_price,
            'tax_rate' => (float) $this->tax_rate,
            'max_discount' => (float) $this->max_discount,
            'discount_percentage' => (float) $this->discount_percentage,
            'brand' => strtoupper(trim($this->brand)),
            'stock' => (float) $this->stock,
            'item_type' => (int) $this->item_type,
            'min_stock' => (float) $this->min_stock,
            'max_stock' => (float) $this->max_stock,
            'is_taxable' => (bool) $this->is_taxable,
            'is_gift' => (bool) $this->is_gift,
            'notes' => trim($this->notes),
            'state' => (int) $this->state,
            'created_at' => $this->created_at->format('Y/m/d h:i:s'),
            'updated_at' => $this->updated_at->format('Y/m/d h:i:s'),
        ];
    }
}
