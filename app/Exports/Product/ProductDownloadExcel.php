<?php

namespace App\Exports\Product;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ProductDownloadExcel implements FromView, WithColumnWidths, WithEvents
{
    protected $groupedProducts;
    protected $totalProducts;
    protected $totalStock;

    public function __construct($groupedProducts, $totalProducts = 0, $totalStock = 0)
    {
        $this->groupedProducts = $groupedProducts;
        $this->totalProducts = $totalProducts;
        $this->totalStock = $totalStock;
    }

    public function view(): View
    {
        return view('product.porduct_download_excel', [
            'groupedProducts' => $this->groupedProducts,
            'totalProducts' => $this->totalProducts,
            'totalStock' => $this->totalStock,
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 45, // Descripción
            'B' => 20, // Código/SKU
            'C' => 18, // Cód. Auxiliar
            'D' => 25, // Usos / Aplicación
            'E' => 18, // Marca
            'F' => 22, // Bodega/Almacén
            'G' => 14, // Unidad
            'H' => 25, // Proveedor
            'I' => 15, // P. Compra
            'J' => 15, // P. Venta
            'K' => 14, // Stock
            'L' => 14, // Stock Mín.
            'M' => 14, // Stock Máx.
            'N' => 12, // IVA
            'O' => 15, // Desc. Máx
            'P' => 12, // Es Regalo
            'Q' => 25, // Notas
            'R' => 14, // Estado
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Configurar estilos adicionales si son requeridos
            },
        ];
    }
}
