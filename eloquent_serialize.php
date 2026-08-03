<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$order = \App\Models\WorkOrder\WorkOrder::orderBy('id', 'desc')->first();
$array = $order->toArray();
echo "created_at in toArray: " . $array['created_at'] . "\n";
echo "Type in toArray: " . gettype($array['created_at']) . "\n";

// Let's call the castAttribute method via reflection or look at attributes
$reflector = new \ReflectionClass($order);
$method = $reflector->getMethod('serializeDate');
$method->setAccessible(true);
echo "serializeDate result: " . $method->invoke($order, $order->created_at) . "\n";

// Let's print the cast definition
print_r($order->getCasts());
