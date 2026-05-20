<?php

namespace App\Imports\Product;

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
         return new Product([
             'name' => $row['name'],
             'description' => $row['description'] ?? null,
             'price' => $row['price'],
             'stock' => $row['stock'] ?? 0,
             'category_id' => $row['category_id'] ?? null,
         ]);
         
     }
     public function rules(): array
     {
         return [
             'name' => 'required|string|max:255',
             'description' => 'nullable|string',
             'price' => 'required|numeric',
             'stock' => 'nullable|integer',
             'category_id' => 'nullable|exists:product_categories,id',
         ];
     }
}