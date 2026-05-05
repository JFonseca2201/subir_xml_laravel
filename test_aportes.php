<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

try {
    // Simular una solicitud básica
    $request = \Illuminate\Http\Request::create('/api/aportes', 'GET');
    
    // Crear instancia del controlador
    $controller = new \App\Http\Controllers\Api\AporteController();
    
    // Ejecutar el método index
    $response = $controller->index($request);
    
    echo "Respuesta: " . $response->getContent() . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
