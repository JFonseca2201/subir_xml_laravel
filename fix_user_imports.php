<?php

$files = [
    'app/Models/Client/Client.php',
    'app/Models/Employee/EmployeeAdvance.php',
    'app/Models/Employee/EmployeePayment.php',
    'app/Models/Finance/FinanceRecord.php',
    'app/Models/Finance/InternalTransfer.php',
    'app/Models/Invoice/Invoice.php',
    'app/Models/Partner/AporteCapital.php',
    'app/Models/WorkOrder/WorkOrder.php',
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'use App\Models\User;') === false && strpos($content, '\App\Models\User::class') === false) {
            $content = preg_replace('/(namespace App\\\\Models\\\\[^;]+;)/', "$1\n\nuse App\Models\User;", $content);
            file_put_contents($file, $content);
            echo "Fixed $file\n";
        }
    }
}
echo "Done.\n";
