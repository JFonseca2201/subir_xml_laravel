<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sale = App\Models\Sales\Sale::with(['client', 'vehicle', 'user', 'details.product', 'technicians', 'financeRecord.paymentDistributions.account'])->find(29);
if (!$sale) {
    echo "Sale 29 not found\n";
    exit(1);
}
$pdf = Barryvdh\DomPDF\Facade\Pdf::loadView('sales.pdf_sale', compact('sale'));
$tempPath = storage_path('app/temp_test_print.pdf');
$pdf->save($tempPath);
echo "Saved to: " . $tempPath . "\n";
