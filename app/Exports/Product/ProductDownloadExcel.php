<?php

namespace App\Exports\Product;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class ProductDownloadExcel implements FromView
{
    protected $products;

    public function __construct($products)
    {
        $this->products = $products;
    }


    public function view(): View
    {
        return view('product.porduct_download_excel', [
            'list_products' => $this->products,
        ]);
    }
}
