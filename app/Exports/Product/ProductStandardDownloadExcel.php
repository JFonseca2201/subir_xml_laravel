<?php

namespace App\Exports\Product;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ProductStandardDownloadExcel implements FromView, WithColumnWidths, WithEvents
{
    protected $products;

    public function __construct($products)
    {
        $this->products = $products;
    }

    public function view(): View
    {
        return view('product.product_download_excel_standard', [
            'list_products' => $this->products,
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 45, // description
            'B' => 20, // sku
            'C' => 18, // code_aux
            'D' => 25, // uses
            'E' => 22, // categoria
            'F' => 22, // bodega
            'G' => 14, // unidad
            'H' => 25, // proveedor
            'I' => 15, // price
            'J' => 15, // price_sale
            'K' => 16, // purchase_price
            'L' => 14, // tax_rate
            'M' => 16, // max_discount
            'N' => 18, // discount_percentage
            'O' => 18, // brand
            'P' => 14, // stock
            'Q' => 14, // item_type
            'R' => 14, // min_stock
            'S' => 14, // max_stock
            'T' => 14, // is_taxable
            'U' => 12, // is_gift
            'V' => 25, // notes
            'W' => 14, // state
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Asegurar formato de celdas y ajuste
                $event->sheet->getDelegate()->getStyle('A1:W1')->getFont()->setBold(true);
            },
        ];
    }
}
