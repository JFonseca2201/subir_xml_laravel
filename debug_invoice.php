<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Inicializar el entorno Laravel
$app->boot();

use Illuminate\Http\Request;
use App\Http\Controllers\Invoice\InvoiceXmlImportController;

// Simular la petición exacta del frontend
$data = [
    'item_type' => 1,
    'categorie_id' => 15
];

$request = new Request([], [], [], [], $data);

$controller = new InvoiceXmlImportController();

try {
    echo "Probando método update con ID 1...\n";
    $response = $controller->update($request, 1);
    echo "Response: " . $response->getContent() . "\n";
    echo "Status: " . $response->getStatusCode() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
