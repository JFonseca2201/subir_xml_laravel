<?php

$files = [
    'app/Models/Employee/EmployeeAdvance.php',
    'app/Models/Employee/EmployeePayment.php',
    'app/Models/Partner/AporteCapital.php',
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'use App\Models\Finance\Account;') === false && strpos($content, '\App\Models\Finance\Account::class') === false) {
            $content = preg_replace('/(namespace App\\\\Models\\\\[^;]+;)/', "$1\n\nuse App\Models\Finance\Account;", $content);
            file_put_contents($file, $content);
            echo "Fixed $file\n";
        }
    }
}
echo "Done.\n";
