<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$s = App\Models\Config\Sucursale::find(1);
echo "=== VERIFICACIÓN TRAS GUARDAR ===\n";
echo "Razón Social: {$s->name}\n";
echo "RUC: {$s->ruc}\n";
echo "Firma Path en BD: " . ($s->firma_electronica ?? 'NULL') . "\n";

if ($s->firma_electronica) {
    $relPath = ltrim($s->firma_electronica, '/');
    $candidates = [
        storage_path('app/' . $relPath),
        storage_path('app/private/' . $relPath),
        storage_path('app/public/' . $relPath),
        storage_path($relPath),
        public_path('storage/' . $relPath),
    ];
    $found = null;
    foreach ($candidates as $cand) {
        if (file_exists($cand)) { $found = $cand; break; }
    }
    if ($found) {
        echo "Archivo físico: EXISTE ({$found})\n";
        echo "Tamaño: " . filesize($found) . " bytes\n";
        $certs = [];
        if (openssl_pkcs12_read(file_get_contents($found), $certs, $s->password_firma ?? '')) {
            echo "Validación Firma OpenSSL: ¡ÉXITO TOTAL!\n";
            $data = openssl_x509_parse($certs['cert']);
            echo "Titular: " . ($data['subject']['CN'] ?? 'N/A') . "\n";
            echo "Emisor: " . ($data['issuer']['CN'] ?? 'N/A') . "\n";
            echo "Vigencia: " . date('Y-m-d', $data['validFrom_time_t'] ?? 0) . " hasta " . date('Y-m-d', $data['validTo_time_t'] ?? 0) . "\n";
        } else {
            echo "Error de contraseña/lectura OpenSSL: " . openssl_error_string() . "\n";
        }
    } else {
        echo "Archivo físico: NO encontrado.\n";
    }
}
