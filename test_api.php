<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/api/employees?page=1&per_page=10&status=active', 'GET');
$user = App\Models\User::first();
if ($user) {
    $app->make('auth')->guard('sanctum')->setUser($user);
}
$response = $kernel->handle($request);
echo "STATUS: " . $response->getStatusCode() . "\n";
echo $response->getContent();
