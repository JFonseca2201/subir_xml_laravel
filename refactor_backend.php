<?php

$baseDir = __DIR__;

$modelsMap = [
    'Partner.php' => 'Partner/Partner.php',
    'AporteCapital.php' => 'Partner/AporteCapital.php',
    'PartnerContribution.php' => 'Partner/PartnerContribution.php',
    
    'Employee.php' => 'Employee/Employee.php',
    'EmployeeAdvance.php' => 'Employee/EmployeeAdvance.php',
    'EmployeePayment.php' => 'Employee/EmployeePayment.php',
    
    'Account.php' => 'Finance/Account.php',
    'FinanceRecord.php' => 'Finance/FinanceRecord.php',
    'FinancialMovement.php' => 'Finance/FinancialMovement.php',
    'InternalTransfer.php' => 'Finance/InternalTransfer.php',
    'MovimientoCuenta.php' => 'Finance/MovimientoCuenta.php',
    'PaymentDistribution.php' => 'Finance/PaymentDistribution.php',
    'Transaction.php' => 'Finance/Transaction.php',
    'Transfer.php' => 'Finance/Transfer.php',
    
    'Supplier.php' => 'Supplier/Supplier.php',
    
    'Invoice.php' => 'Invoice/Invoice.php',
    'InvoiceItem.php' => 'Invoice/InvoiceItem.php',
    
    'WorkOrder.php' => 'WorkOrder/WorkOrder.php',
    'WorkOrderItem.php' => 'WorkOrder/WorkOrderItem.php'
];

$controllersMap = [
    'PartnerController.php' => 'Partner/PartnerController.php',
    'PartnerContributionController.php' => 'Partner/PartnerContributionController.php',
    
    'AporteController.php' => 'Aporte/AporteController.php',
    
    'AccountController.php' => 'Finance/AccountController.php',
    'FinanceRecordController.php' => 'Finance/FinanceRecordController.php',
    'FinanzasController.php' => 'Finance/FinanzasController.php',
    'InternalTransferController.php' => 'Finance/InternalTransferController.php',
    'TransferController.php' => 'Finance/TransferController.php',
    
    'EmployeeExpenseController.php' => 'Employee/EmployeeExpenseController.php',
    'EmployeePaymentController.php' => 'Employee/EmployeePaymentController.php',
    
    'GeographicController.php' => 'Geographic/GeographicController.php',
    
    'WorkOrderController.php' => 'WorkOrder/WorkOrderController.php'
];

// Reemplazos que aplicaremos globalmente
$globalReplacements = [];

// 1. Mover Modelos
foreach ($modelsMap as $oldFile => $newFile) {
    $oldPath = $baseDir . '/app/Models/' . $oldFile;
    $newPath = $baseDir . '/app/Models/' . $newFile;
    
    if (file_exists($oldPath)) {
        $dir = dirname($newPath);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        
        rename($oldPath, $newPath);
        
        // Actualizar namespace dentro del archivo movido
        $content = file_get_contents($newPath);
        $newNamespace = 'App\\Models\\' . explode('/', $newFile)[0];
        $content = preg_replace('/namespace\s+App\\\\Models\s*;/', "namespace $newNamespace;", $content);
        file_put_contents($newPath, $content);
        
        // Preparar reemplazo global
        $className = str_replace('.php', '', $oldFile);
        $globalReplacements['App\\Models\\' . $className] = $newNamespace . '\\' . $className;
    }
}

// 2. Mover Controladores
foreach ($controllersMap as $oldFile => $newFile) {
    $oldPath = $baseDir . '/app/Http/Controllers/Api/' . $oldFile;
    $newPath = $baseDir . '/app/Http/Controllers/Api/' . $newFile;
    
    if (file_exists($oldPath)) {
        $dir = dirname($newPath);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        
        rename($oldPath, $newPath);
        
        // Actualizar namespace dentro del archivo
        $content = file_get_contents($newPath);
        $newNamespace = 'App\\Http\\Controllers\\Api\\' . explode('/', $newFile)[0];
        $content = preg_replace('/namespace\s+App\\\\Http\\\\Controllers\\\\Api\s*;/', "namespace $newNamespace;", $content);
        file_put_contents($newPath, $content);
        
        // Preparar reemplazo global
        $className = str_replace('.php', '', $oldFile);
        $globalReplacements['App\\Http\\Controllers\\Api\\' . $className] = $newNamespace . '\\' . $className;
    }
}

// 3. Reemplazar referencias globales
function scanAndReplace($dir, $replacements) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());
            $modified = false;
            
            // Especial cuidado con los "use App\Models\Partner;" 
            // Necesitamos usar word boundaries o asegurar que no coincidan de forma parcial
            // Ordenar por longitud descendente para evitar reemplazos parciales
            uksort($replacements, function($a, $b) {
                return strlen($b) - strlen($a);
            });
            
            foreach ($replacements as $oldClass => $newClass) {
                // Reemplazo en las sentencias "use" y en resoluciones completas
                $escapedOldClass = str_replace('\\', '\\\\', $oldClass);
                $escapedNewClass = str_replace('\\', '\\', $newClass); // en reemplazo string de PHP, un \ es suficiente
                
                // Regex para coincidir con la clase exacta (usando delimitador de palabra o final de linea/;)
                $pattern = '/(?<![a-zA-Z0-9_\\\\])' . $escapedOldClass . '(?![a-zA-Z0-9_\\\\])/';
                
                if (preg_match($pattern, $content)) {
                    $content = preg_replace($pattern, $newClass, $content);
                    $modified = true;
                }
            }
            
            if ($modified) {
                file_put_contents($file->getPathname(), $content);
                echo "Updated references in: " . $file->getPathname() . "\n";
            }
        }
    }
}

scanAndReplace($baseDir . '/app', $globalReplacements);
scanAndReplace($baseDir . '/routes', $globalReplacements);
scanAndReplace($baseDir . '/database', $globalReplacements);

echo "Refactorización completada correctamente.\n";
