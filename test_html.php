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
// Merge print parameter to request
request()->merge(['print' => 'true']);
// Render view to HTML string
$html = view('sales.pdf_sale', compact('sale'))->render();
$tempPath = storage_path('app/temp_test.html');
file_put_contents($tempPath, $html);
echo "Saved HTML with print script to: " . $tempPath . "\n";
