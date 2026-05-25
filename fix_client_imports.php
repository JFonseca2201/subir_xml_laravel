<?php
$file = 'app/Models/WorkOrder/WorkOrder.php';
$content = file_get_contents($file);
if (strpos($content, 'use App\Models\Client\Client;') === false) {
    $content = preg_replace('/(namespace App\\\\Models\\\\[^;]+;)/', "$1\n\nuse App\Models\Client\Client;", $content);
    file_put_contents($file, $content);
    echo "Fixed Client in $file\n";
}
