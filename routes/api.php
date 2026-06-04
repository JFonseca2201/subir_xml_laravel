<?php

use App\Http\Controllers\Api\Finance\AccountController;
use App\Http\Controllers\Api\Aporte\AporteController;
use App\Http\Controllers\Api\Employee\EmployeeExpenseController;
use App\Http\Controllers\Api\Employee\EmployeePaymentController;
use App\Http\Controllers\Api\Finance\FinanceRecordController;
use App\Http\Controllers\Api\Finance\FinanzasController;
use App\Http\Controllers\Api\Finance\InternalTransferController;
use App\Http\Controllers\Api\Partner\PartnerContributionController;
use App\Http\Controllers\Api\Partner\PartnerController;
use App\Http\Controllers\Api\Finance\TransferController;
use App\Http\Controllers\Api\WorkOrder\WorkOrderController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Client\ClientController;
use App\Http\Controllers\Config\ProductCategorieController;
use App\Http\Controllers\Config\SucursaleController;
use App\Http\Controllers\Config\SupplierController;
use App\Http\Controllers\Config\UnitConversionController;
use App\Http\Controllers\Config\UnitController;
use App\Http\Controllers\Config\WarehouseController;
use App\Http\Controllers\Employee\EmployeeController;
use App\Http\Controllers\Invoice\InvoiceXmlImportController;
use App\Http\Controllers\KardexController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Sales\ProductReturnController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Vehicle\VehicleController;
use App\Http\Controllers\Api\Geographic\GeographicController;
use App\Http\Controllers\ExcelImportController;
use App\Http\Controllers\DashboardController;
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

        // ============= RUTAS DE FACTURAS / COMPRAS ==============
        Route::post('invoices/import-xml', [InvoiceXmlImportController::class, 'store']);
        Route::post('purchases/manual', [\App\Http\Controllers\Purchases\PurchaseManualController::class, 'store']);
        Route::post('invoices/index', [InvoiceXmlImportController::class, 'index']);
        Route::get('invoices/config', [InvoiceXmlImportController::class, 'config']);
        Route::get('/invoices/{id}', [InvoiceXmlImportController::class, 'show']);
        Route::put('/invoices/{id}', [InvoiceXmlImportController::class, 'update']);
        Route::delete('/invoices/{id}', [InvoiceXmlImportController::class, 'destroy']);
        Route::put('/invoice-items/{id}', [InvoiceXmlImportController::class, 'updateType']);

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

        // ============= RUTAS GEOGRÁFICAS ==============
        Route::get('geographic/regions', [GeographicController::class, 'getRegions']);
        Route::get('geographic/provinces/{regionId}', [GeographicController::class, 'getProvinces']);
        Route::get('geographic/cities/{provinceId}', [GeographicController::class, 'getCities']);

        // ============= RUTAS DE VEHÍCULOS ==============
        Route::resource('vehicles', VehicleController::class);
        Route::get('vehicle-types', [VehicleController::class, 'getVehicleTypes']);
        Route::get('vehicle-brands', [VehicleController::class, 'getVehicleBrands']);

        // ============= RUTAS DE CUENTAS ================
        Route::resource('accounts', AccountController::class);

        // ============= RUTAS DE CONTRIBUCIONES ==========
        Route::resource('contributions', PartnerContributionController::class);

        // ============= RUTAS DE EMPLEADOS ==============
        Route::resource('employees', EmployeeController::class);
        Route::post('employees/{id}/restore', [EmployeeController::class, 'restore']);
        Route::delete('employees/{id}/force-delete', [EmployeeController::class, 'forceDelete']);

        // ============= RUTAS DE GASTOS DE EMPLEADOS ==========
        Route::get('employee-expenses', [EmployeeExpenseController::class, 'index']);
        Route::post('employee-expenses', [EmployeeExpenseController::class, 'store']);
        Route::put('employee-expenses/{id}', [EmployeeExpenseController::class, 'update']);
        Route::delete('employee-expenses/{id}', [EmployeeExpenseController::class, 'destroy']);
        Route::get('employee-earnings/{id}', [EmployeeExpenseController::class, 'getEmployeeEarnings']);
        Route::get('employee-pending-advances/{id}', [EmployeeExpenseController::class, 'getEmployeePendingAdvances']);

        // ============= RUTAS DE ADELANTOS ==========
        Route::post('employee-expenses/advance', [EmployeeExpenseController::class, 'storeAdvance']);
        Route::put('employee-expenses/{id}/advance', [EmployeeExpenseController::class, 'updateAdvance']);
        Route::delete('employee-expenses/{id}/advance', [EmployeeExpenseController::class, 'destroyAdvance']);

        // ============= RUTAS DE TRANSFERENCIAS ==========
        //Route::resource('transfers', TransferController::class);
        Route::resource('transfers', InternalTransferController::class);

        // ============= RUTAS DE FINANCE RECORDS ==========
        Route::get('finance-records', [FinanceRecordController::class, 'index']);
        Route::post('finance-records', [FinanceRecordController::class, 'store']);
        Route::get('finance-records/{financeRecord}', [FinanceRecordController::class, 'show']);
        Route::put('finance-records/{financeRecord}', [FinanceRecordController::class, 'update']);
        Route::delete('finance-records/{financeRecord}', [FinanceRecordController::class, 'destroy']);
        Route::delete('payment-distributions/{paymentDistribution}', [FinanceRecordController::class, 'destroyPaymentDistribution']);
        Route::get('finance-records/daily-summary', [FinanceRecordController::class, 'dailySummary']);
        Route::get('finance-records/monthly-stats', [FinanceRecordController::class, 'monthlyStats']);
        Route::get('finance-records/grouped-by-work-order', [FinanceRecordController::class, 'groupedByWorkOrder']);

        // ============= RUTAS DE APORTES DE CAPITAL ==========
        Route::get('aportes', [AporteController::class, 'index']);
        Route::post('aportes', [AporteController::class, 'store']);
        Route::put('aportes/{id}', [AporteController::class, 'update']);
        Route::delete('aportes/{id}', [AporteController::class, 'destroy']);

        // ============= RUTAS DE DASHBOARD FINANCIERO ==========
        Route::get('dashboard-financiero', [FinanzasController::class, 'getDashboardData']);
        Route::post('financial-movements/pdf', [FinanzasController::class, 'generatePDF']);

        // ============= RUTAS DE SALES ==========
        Route::get('sales/next-number', [SaleController::class, 'getNextNumber']);
        Route::delete('sales/details/{id}', [SaleController::class, 'destroyDetail']);
        Route::resource('sales', SaleController::class);
        Route::post('sales/dispatch', [SaleController::class, 'dispatchSale']);
        Route::post('sales/{id}/register-payment', [SaleController::class, 'registerPayment']);

        // ============= RUTAS DE DEVOLUCIONES ==========
        Route::get('returns', [ProductReturnController::class, 'index']);
        Route::post('returns', [ProductReturnController::class, 'store']);
        Route::get('returns/{id}', [ProductReturnController::class, 'show']);
        Route::delete('returns/{id}', [ProductReturnController::class, 'destroy']);

        // ============= RUTAS DE ÓRDENES DE TRABAJO ==========
        Route::get('work-orders/next-number', [WorkOrderController::class, 'getNextNumber']);
        Route::get('work-orders/ready-to-invoice', [WorkOrderController::class, 'getReadyToInvoice']);
        Route::put('work-orders/{id}/status', [WorkOrderController::class, 'updateStatus']);
        Route::get('work-orders/{id}/pdf', [WorkOrderController::class, 'generatePDF']);
        Route::resource('work-orders', WorkOrderController::class);


        // Excel Import Routes para Clientes y Vehículos
        Route::post('import/clients', [ExcelImportController::class, 'importClients']);
        Route::post('import/vehicles', [ExcelImportController::class, 'importVehicles']);

        Route::post('sales/pdf', [SaleController::class, 'generatePDF']);
        Route::get('sales/{id}/pdf', [SaleController::class, 'generateSinglePDF']);

        // ============= RUTAS DE PEDIDOS A DISTRIBUIDOR ========== 
        Route::get('pedidos-distribuidor/next-number', [\App\Http\Controllers\Supplier\PedidoDistribuidorController::class, 'getNextNumber']);
        Route::get('pedidos-distribuidor/productos/{distribuidor_id}', [\App\Http\Controllers\Supplier\PedidoDistribuidorController::class, 'getProductsBySupplier']);
        Route::post('pedidos-distribuidor', [\App\Http\Controllers\Supplier\PedidoDistribuidorController::class, 'store']);
        Route::get('pedidos-distribuidor', [\App\Http\Controllers\Supplier\PedidoDistribuidorController::class, 'index']);
        Route::get('pedidos-distribuidor/{id}', [\App\Http\Controllers\Supplier\PedidoDistribuidorController::class, 'show']);
        Route::get('pedidos-distribuidor/{id}/pdf', [\App\Http\Controllers\Supplier\PedidoDistribuidorController::class, 'generatePDF']);
        Route::put('pedidos-distribuidor/{id}', [\App\Http\Controllers\Supplier\PedidoDistribuidorController::class, 'update']);
        Route::delete('pedidos-distribuidor/{id}', [\App\Http\Controllers\Supplier\PedidoDistribuidorController::class, 'destroy']);
        Route::put('pedidos-distribuidor/{id}/status', [\App\Http\Controllers\Supplier\PedidoDistribuidorController::class, 'updateStatus']);

        // ============= RUTAS DE KARDEX INTEGRAL ==========
        Route::get('kardex/productos', [KardexController::class, 'indexByProduct']);
        Route::get('kardex', [KardexController::class, 'index']);

        // ============= RUTAS DE DASHBOARD GENERAL ==========
        Route::get('dashboard/search', [DashboardController::class, 'search']);
        Route::get('dashboard', [DashboardController::class, 'index']);
    },

);
Route::get('products-excel', [ProductController::class, 'download_excel']);
Route::post('products/import-excel', [ProductController::class, 'import_excel']);
