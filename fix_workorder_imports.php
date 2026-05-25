<?php
$file = 'app/Models/WorkOrder/WorkOrder.php';
$content = file_get_contents($file);
if (strpos($content, 'use App\Models\Employee\Employee;') === false) {
    $content = preg_replace('/(namespace App\\\\Models\\\\[^;]+;)/', "$1\n\nuse App\Models\Employee\Employee;", $content);
    file_put_contents($file, $content);
    echo "Fixed $file\n";
}
