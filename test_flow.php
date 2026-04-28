<?php

require_once 'vendor/autoload.php';

use App\Models\DailyCashFlow;
use App\Models\Account;
use App\Models\User;

// Inicializar Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Creando DailyCashFlow con fecha 2026-04-23 ===\n";

try {
    // Crear el flujo
    $flow = DailyCashFlow::create([
        'flow_type' => 'income',
        'flow_date' => '2026-04-23',
        'total_amount' => 500.00,
        'payment_status' => 'complete',
        'description' => 'Venta de producto',
        'account_type' => 1,
        'account_id' => 1,
        'user_id' => 1,
        'source_type' => 'sale'
    ]);

    echo "✅ Flujo creado exitosamente\n";
    echo "ID: " . $flow->id . "\n";
    echo "Fecha: " . $flow->flow_date . "\n";
    echo "Monto: " . $flow->total_amount . "\n";
    echo "Descripción: " . $flow->description . "\n";

    // Cargar relaciones
    $flowWithRelations = $flow->load(['account', 'user']);
    
    echo "\n=== Respuesta completa del backend ===\n";
    echo json_encode($flowWithRelations, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
}
