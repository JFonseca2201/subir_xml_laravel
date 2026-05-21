<?php

namespace App\Imports\Product;

use App\Models\Config\ProductCategorie;
use App\Models\Product\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ImportProducts implements ToModel, WithHeadingRow, WithValidation
{
    /**
    * @param Collection $collection
    */
     use Importable, SkipsErrors, SkipsFailures;

     public function model(array $row)
     {
        $product_categorie = ProductCategorie::where('name', $row['categoria'])->first();
        if (!$product_categorie) {
            return Product::first();
        }
         $PRODUCT = Product::create([
            'description' => $row['description'], //name
            'sku' => $row['sku'],
            'imagen' => $row['imagen'],
            'code_aux' => $row['code_aux'],
            'uses' => $row['uses'],
            'product_categorie_id' =>  $product_categorie->id,
            'warehouse_id' => $row['warehouse_id']=='LUXURY EVYS' ? 1 : 2,
            'unit_id' => $row['unidad'],
            'supplier_id' => $row['supplier_id'],
            'price' => $row['price'],
            'price_sale' => $row['price_sale'],
            'purchase_price' => $row['purchase_price'],
            'tax_rate' => $row['tax_rate'],
            'max_discount' => $row['max_discount'] ? $row['max_discount'] : 0,
            'discount_percentage' => $row['discount_percentage'] ? $row['discount_percentage'] : 0,
            'brand' => $row['brand'],
            'stock' => $row['stock'],
            'item_type' => $row['item_type'],
            'min_stock' => $row['min_stock'],
            'max_stock' => $row['max_stock'],
            'is_taxable' => $row['is_taxable']=="Si" ? 1 : 2,
            'is_gift' => $row['is_gift']=="Si" ? 1 : 2,
            'notes' => $row['notes'],
            'state' => $row['state']=="Activo" ? 1 : 2,
         ]);
         return $PRODUCT;
         
     }
     public function rules(): array
     {
         return [             
             '*.description' => 'nullable|string',
             '*.sku' => 'required|string',
             '*.imagen' => 'nullable|string',
             '*.code_aux' => 'nullable|string',
             '*.uses' => 'nullable|string',
             '*.categoria' => 'required|exists:product_categories,name',
             '*.warehouse_id' => 'required|exists:warehouses,id',
             '*.unidad' => 'nullable|exists:units,id',
             '*.supplier_id' => 'nullable|exists:suppliers,id',
             '*.price' => 'required|numeric',
             '*.price_sale' => 'nullable|numeric',
             '*.purchase_price' => 'nullable|numeric',
             '*.tax_rate' => 'nullable|numeric',
             '*.max_discount' => 'nullable|numeric',
             '*.discount_percentage' => 'nullable|numeric',
             '*.brand' => 'nullable|string',
             '*.stock' => 'nullable|integer',
             '*.item_type' => 'nullable|string',
             '*.min_stock' => 'nullable|integer',
             '*.max_stock' => 'nullable|integer',
             '*.is_taxable' => 'nullable|boolean',
             '*.is_gift' => 'nullable|boolean',
             '*.notes' => 'nullable|string',
             '*.state' => 'nullable|string',
         ];
     }
}