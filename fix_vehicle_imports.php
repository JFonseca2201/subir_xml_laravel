<?php
$file = 'app/Models/WorkOrder/WorkOrder.php';
$content = file_get_contents($file);
if (strpos($content, 'use App\Models\Vehicles\Vehicle;') === false) {
    $content = preg_replace('/(namespace App\\\\Models\\\\[^;]+;)/', "$1\n\nuse App\Models\Vehicles\Vehicle;", $content);
    file_put_contents($file, $content);
    echo "Fixed Vehicle in $file\n";
}
