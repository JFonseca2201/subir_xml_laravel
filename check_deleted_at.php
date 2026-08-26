<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

$hasDeletedAt = Schema::hasColumn('sales', 'deleted_at');
echo "Sales has deleted_at column: " . ($hasDeletedAt ? "YES" : "NO") . "\n";
