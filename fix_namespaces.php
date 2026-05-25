<?php

$baseDir = __DIR__;

// Mapeo inverso para arreglar namespaces de modelos
$fixes = [
    'namespace App\Models\Employee\Employee;' => 'namespace App\Models\Employee;',
    'namespace App\Models\Employee\EmployeeAdvance;' => 'namespace App\Models\Employee;',
    'namespace App\Models\Employee\EmployeePayment;' => 'namespace App\Models\Employee;',
    
    'namespace App\Models\Finance\Account;' => 'namespace App\Models\Finance;',
    'namespace App\Models\Finance\FinanceRecord;' => 'namespace App\Models\Finance;',
    'namespace App\Models\Finance\FinancialMovement;' => 'namespace App\Models\Finance;',
    'namespace App\Models\Finance\InternalTransfer;' => 'namespace App\Models\Finance;',
    'namespace App\Models\Finance\MovimientoCuenta;' => 'namespace App\Models\Finance;',
    'namespace App\Models\Finance\PaymentDistribution;' => 'namespace App\Models\Finance;',
    'namespace App\Models\Finance\Transaction;' => 'namespace App\Models\Finance;',
    'namespace App\Models\Finance\Transfer;' => 'namespace App\Models\Finance;',
    
    'namespace App\Models\Invoice\Invoice;' => 'namespace App\Models\Invoice;',
    'namespace App\Models\Invoice\InvoiceItem;' => 'namespace App\Models\Invoice;',
    
    'namespace App\Models\Partner\AporteCapital;' => 'namespace App\Models\Partner;',
    'namespace App\Models\Partner\Partner;' => 'namespace App\Models\Partner;',
    'namespace App\Models\Partner\PartnerContribution;' => 'namespace App\Models\Partner;',
    
    'namespace App\Models\Supplier\Supplier;' => 'namespace App\Models\Supplier;',
    
    'namespace App\Models\WorkOrder\WorkOrder;' => 'namespace App\Models\WorkOrder;',
    'namespace App\Models\WorkOrder\WorkOrderItem;' => 'namespace App\Models\WorkOrder;'
];

// Mapeo inverso para arreglar namespaces de controladores
$fixesControllers = [
    'namespace App\Http\Controllers\Api\Aporte\AporteController;' => 'namespace App\Http\Controllers\Api\Aporte;',
    
    'namespace App\Http\Controllers\Api\Employee\EmployeeExpenseController;' => 'namespace App\Http\Controllers\Api\Employee;',
    'namespace App\Http\Controllers\Api\Employee\EmployeePaymentController;' => 'namespace App\Http\Controllers\Api\Employee;',
    
    'namespace App\Http\Controllers\Api\Finance\AccountController;' => 'namespace App\Http\Controllers\Api\Finance;',
    'namespace App\Http\Controllers\Api\Finance\FinanceRecordController;' => 'namespace App\Http\Controllers\Api\Finance;',
    'namespace App\Http\Controllers\Api\Finance\FinanzasController;' => 'namespace App\Http\Controllers\Api\Finance;',
    'namespace App\Http\Controllers\Api\Finance\InternalTransferController;' => 'namespace App\Http\Controllers\Api\Finance;',
    'namespace App\Http\Controllers\Api\Finance\TransferController;' => 'namespace App\Http\Controllers\Api\Finance;',
    
    'namespace App\Http\Controllers\Api\Geographic\GeographicController;' => 'namespace App\Http\Controllers\Api\Geographic;',
    
    'namespace App\Http\Controllers\Api\Partner\PartnerContributionController;' => 'namespace App\Http\Controllers\Api\Partner;',
    'namespace App\Http\Controllers\Api\Partner\PartnerController;' => 'namespace App\Http\Controllers\Api\Partner;',
    
    'namespace App\Http\Controllers\Api\WorkOrder\WorkOrderController;' => 'namespace App\Http\Controllers\Api\WorkOrder;'
];

$allFixes = array_merge($fixes, $fixesControllers);

function fixNamespaces($dir, $fixes) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());
            $modified = false;
            
            foreach ($fixes as $bad => $good) {
                if (strpos($content, $bad) !== false) {
                    $content = str_replace($bad, $good, $content);
                    $modified = true;
                }
            }
            
            if ($modified) {
                file_put_contents($file->getPathname(), $content);
                echo "Fixed namespace in: " . $file->getPathname() . "\n";
            }
        }
    }
}

fixNamespaces($baseDir . '/app', $allFixes);
echo "Namespaces arreglados.\n";
