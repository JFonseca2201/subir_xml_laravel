<?php

namespace App\Imports\Product;

use App\Models\Config\ProductCategorie;
use App\Models\Config\Unit;
use App\Models\Config\Warehouse;
use App\Models\Product\Product;
use App\Models\Supplier\Supplier;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Str;

class ImportProducts implements ToModel, WithHeadingRow, WithValidation
{
    /**
    * @param Collection $collection
    */
     use Importable, SkipsErrors, SkipsFailures;

     public function prepareForValidation($data, $index)
     {
         if (isset($data['supplier_id']) && (strtolower(trim($data['supplier_id'])) === 'null' || trim($data['supplier_id']) === '')) {
             $data['supplier_id'] = null;
         }
         return $data;
     }

     public function model(array $row)
     {
        $description = strtoupper(trim($row['description'] ?? ''));
        $sku = strtoupper(trim((string)$row['sku'] ?? ''));

        // Validar duplicidad por descripción (nombre) o por SKU (código)
        $isDuplicate = Product::where('description', $description)
            ->when(!empty($sku), function ($query) use ($sku) {
                return $query->orWhere('sku', $sku);
            })->exists();

        if ($isDuplicate) {
            return null;
        }

        $categoriaName = trim($row['categoria'] ?? '');
        $product_categorie = ProductCategorie::where('title', 'like', '%' . $categoriaName . '%')->first();

        if (!$product_categorie && !empty($categoriaName)) {
            $product_categorie = ProductCategorie::create([
                'title' => strtoupper($categoriaName),
                'state' => 1 // Asumimos 1 como estado activo por defecto
            ]);
        }
        
        $warehouse = Warehouse::where('name', 'like', '%' . trim($row['almacen'] ?? '') . '%')->first();

        $unit = Unit::where('name', 'like', '%' . trim($row['unidad'] ?? '') . '%')->first();

        $distribuidor = !empty($row['supplier_id']) ? Supplier::find($row['supplier_id']) : null;

         switch (Str::lower(trim($row['item_type'] ?? ''))) {
             case 'producto':
                 $item_type = 1;
                 break;
             case 'servicio':
                 $item_type = 2;
                 break;
             default:
                 $item_type = 1; // Valor por defecto
         }   


         return new Product([
            'description' => $description,
            'sku' => $sku,
            'imagen' => $row['imagen'] ?? null,
            'code_aux' => strtoupper(trim($row['code_aux'] ?? '')),
            'uses' => $row['uses'] ?? null,
            'product_categorie_id' => $product_categorie->id,
            'warehouse_id' => $warehouse ? $warehouse->id : 1,
            'unit_id' => $unit ? $unit->id : 9,
            'supplier_id' => $distribuidor ? $distribuidor->id : null,
            'price' => floatval($row['price'] ?? 0),
            'price_sale' => floatval($row['price_sale'] ?? 0),
            'purchase_price' => floatval($row['purchase_price'] ?? 0),
            'tax_rate' => floatval($row['tax_rate'] ?? 15),
            'max_discount' => floatval($row['max_discount'] ?? 0),
            'discount_percentage' => floatval($row['discount_percentage'] ?? 0),
            'brand' => strtoupper(trim($row['marca'] ?? '')),
            'stock' => floatval($row['stock'] ?? 0),
            'item_type' => $item_type,
            'min_stock' => floatval($row['min_stock'] ?? 0),
            'max_stock' => floatval($row['max_stock'] ?? 0),
            'is_taxable' => (strcasecmp(trim($row['is_taxable'] ?? ''), 'si') === 0) ? 1 : 2,
            'is_gift' => (strcasecmp(trim($row['is_gift'] ?? ''), 'si') === 0) ? 1 : 2,
            'notes' => $row['notes'] ?? null,
            'state' => (strcasecmp(trim($row['state'] ?? 'activo'), 'activo') === 0) ? 1 : 2,
         ]);
     }
     public function rules(): array
     {
         return [             
             '*.description' => 'required|string',
             '*.sku' => 'required',
             '*.imagen' => 'nullable|string',
             '*.code_aux' => 'nullable|string',
             '*.uses' => 'nullable|string',
             '*.categoria' => 'required|string',
             '*.almacen' => 'nullable|exists:warehouses,name',
             '*.unidad' => 'nullable|exists:units,name',
             '*.supplier_id' => 'nullable|exists:suppliers,id',
             '*.price' => 'required|numeric',
             '*.price_sale' => 'nullable|numeric',
             '*.purchase_price' => 'nullable|numeric',
             '*.tax_rate' => 'nullable|numeric',
             '*.max_discount' => 'nullable|numeric',
             '*.discount_percentage' => 'nullable|numeric',
             '*.marca' => 'nullable|string',
             '*.stock' => 'nullable|integer',
             '*.item_type' => 'nullable|string',
             '*.min_stock' => 'nullable|integer',
             '*.max_stock' => 'nullable|integer',
             '*.is_taxable' => 'nullable|string',
             '*.is_gift' => 'nullable|string',
             '*.notes' => 'nullable|string',
             '*.state' => 'nullable|string',
         ];
     }
}