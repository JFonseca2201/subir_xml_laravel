<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AccountTransferController;
use App\Http\Controllers\Api\CashSessionController;
use App\Http\Controllers\Api\DailyCashFlowController;
use App\Http\Controllers\Api\EmployeeAdvanceController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeePaymentController;
use App\Http\Controllers\Api\PartnerContributionController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Client\ClientController;
use App\Http\Controllers\Config\ProductCategorieController;
use App\Http\Controllers\Config\SucursaleController;
use App\Http\Controllers\Config\SupplierController;
use App\Http\Controllers\Config\UnitConversionController;
use App\Http\Controllers\Config\UnitController;
use App\Http\Controllers\Config\WarehouseController;
use App\Http\Controllers\Invoice\InvoiceXmlImportController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Vehicle\VehicleController;
use Illuminate\Support\Facades\Route;

Route::group(
    [
        /* 'middleware' => 'api', */
        'prefix' => 'auth',
        /* 'middleware' => ['auth:api', 'permission:publish articles'], */
    ],
    function ($router) {
        Route::post('/register', [AuthController::class, 'register'])->name('register');
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/logout', [AuthController::class, 'logout'])
            ->middleware('auth:api')
            ->name('logout');
        Route::post('/refresh', [AuthController::class, 'refresh'])
            ->middleware('auth:api')
            ->name('refresh');
        Route::post('/me', [AuthController::class, 'me'])
            ->middleware('auth:api')
            ->name('me');
    },
);

Route::group(
    [
        'middleware' => ['auth:api'],
    ],
    function () {
        // ============= RUTAS DE ROLES ================
        Route::resource('role', RoleController::class);

        // ============= RUTAS DE USUARIOS ==============
        Route::resource('users', UserController::class);

        // ============= RUTAS DE SUCURSALES ==============
        Route::get('sucursales/{id}', [SucursaleController::class, 'show']);
        Route::put('sucursales/{id}', [SucursaleController::class, 'update']);

        // ============= RUTAS DE ALMACENES ==============
        Route::resource('warehouses', WarehouseController::class);

        // ============= RUTAS DE UNIDADES ==============
        Route::resource('units', UnitController::class);

        // ============= RUTAS DE CONVERSIONES DE UNIDADES ==============
        Route::resource('unit-conversions', UnitConversionController::class);

        // ============= RUTAS DE CATEGORÍAS ==============
        Route::post('categories/{id}', [ProductCategorieController::class, 'update']);
        Route::resource('categories', ProductCategorieController::class);

        // ============= RUTAS DE FACTURAS ==============
        Route::post('invoices/import-xml', [InvoiceXmlImportController::class, 'store']);
        Route::post('invoices/index', [InvoiceXmlImportController::class, 'index']);
        Route::get('invoices/config', [InvoiceXmlImportController::class, 'config']);
        Route::get('/invoices/{id}', [InvoiceXmlImportController::class, 'show']);
        Route::put('/invoices/{id}', [InvoiceXmlImportController::class, 'updateType']);

        // ============= RUTAS DE PARTNERS ==============
        Route::post('partners/index', [PartnerController::class, 'index']);
        Route::resource('partners', PartnerController::class);

        // ============= RUTAS DE PROVEEDORES ============
        Route::resource('suppliers', SupplierController::class);
        Route::get('suppliers/last-id', [SupplierController::class, 'getLastId']);

        // ============= RUTAS DE PRODUCTOS ==============
        Route::get('products/config', [ProductController::class, 'config']);
        Route::post('products/process', [InvoiceXmlImportController::class, 'processInvoice']);
        Route::resource('products', ProductController::class);

        // ============= RUTAS DE CLIENTES ==============
        Route::resource('clients', ClientController::class);

        // ============= RUTAS DE VEHÍCULOS ==============
        Route::resource('vehicles', VehicleController::class);
        Route::get('vehicle-types', [VehicleController::class, 'getVehicleTypes']);
        Route::get('vehicle-brands', [VehicleController::class, 'getVehicleBrands']);

        // ============= RUTAS DE CUENTAS ================
        Route::resource('accounts', AccountController::class);

        // ============= RUTAS DE EMPLEADOS ================
        Route::resource('employees', EmployeeController::class);


        // ============= RUTAS DE CONTRIBUCIONES ==========
        Route::resource('contributions', PartnerContributionController::class);

        // ============= RUTAS DE PAGOS EMPLEADOS =========
        Route::resource('employee-payments', EmployeePaymentController::class);
        Route::get('employees/{employeeId}/check-pending-advances', [EmployeePaymentController::class, 'checkPendingAdvances']);
        Route::get('available-accounts', [EmployeePaymentController::class, 'getAvailableAccounts']);

        // ============= RUTAS DE ADELANTOS DE EMPLEADOS =========
        Route::resource('employee-advances', EmployeeAdvanceController::class);
        Route::get('employees/{employeeId}/pending-advances', [EmployeeAdvanceController::class, 'getPendingAdvances']);
        Route::post('employee-advances/discount', [EmployeeAdvanceController::class, 'discountAdvances']);

        // ============= RUTAS DE TRANSFERENCIAS ==========
        Route::resource('account-transfers', AccountTransferController::class);
        Route::resource('transfers', TransferController::class);
        Route::get('transfer-accounts', [TransferController::class, 'getAvailableAccounts']);

        // ============= RUTAS DE FLUJO DE CAJA DIARIO ==========
        Route::get('cash-flow-options', [DailyCashFlowController::class, 'getOptions']);
        Route::get('daily-cash-flows/summary', [DailyCashFlowController::class, 'dailySummary']);
        Route::get('daily-cash-flows/monthly', [DailyCashFlowController::class, 'monthlyTransactions']);
        Route::resource('daily-cash-flows', DailyCashFlowController::class);

        // ============= RUTAS DE CAJA DIARIA ==========
        Route::resource('cash-sessions', CashSessionController::class);
        Route::get('cash-sessions/open', [CashSessionController::class, 'checkOpen']);
        Route::post('cash-sessions/open', [CashSessionController::class, 'open']);
        Route::post('cash-sessions/{sessionId}/movements', [CashSessionController::class, 'addMovement']);
        Route::get('cash-sessions/summary', [CashSessionController::class, 'getSummary']);
        Route::post('cash-sessions/close', [CashSessionController::class, 'close']);
        Route::get('cash-sessions/history', [CashSessionController::class, 'history']);
        Route::get('cash-sessions/{sessionId}', [CashSessionController::class, 'show']);
        Route::get('cash-sessions/open-sessions', [CashSessionController::class, 'openSessions']);
    },
);
Route::get('products-excel', [ProductController::class, 'download_excel']);
