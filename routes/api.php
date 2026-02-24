<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\EmployeePaymentController;
use App\Http\Controllers\Api\PartnerContributionController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Config\ProductCategorieController;
use App\Http\Controllers\Config\SucursaleController;
use App\Http\Controllers\Config\WarehouseController;
use App\Http\Controllers\Invoice\InvoiceXmlImportController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\User\UserController;
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
        Route::resource('role', RoleController::class);
        Route::resource('users', UserController::class);
        Route::get('sucursales/{id}', [SucursaleController::class, 'show']);
        Route::put('sucursales/{id}', [SucursaleController::class, 'update']);
        Route::resource('warehouses', WarehouseController::class);

        Route::post('categories/{id}', [ProductCategorieController::class, 'update']);
        Route::resource('categories', ProductCategorieController::class);

        Route::post('invoices/import-xml', [InvoiceXmlImportController::class, 'store']);
        Route::post('invoices/index', [InvoiceXmlImportController::class, 'index']);
        Route::get('invoices/config', [InvoiceXmlImportController::class, 'config']);
        Route::get('/invoices/{id}', [InvoiceXmlImportController::class, 'show']);
        Route::put('/invoices/{id}', [InvoiceXmlImportController::class, 'updateType']);

        Route::post('partners/index', [PartnerController::class, 'index']);
        Route::resource('partners', PartnerController::class);
        Route::resource('accounts', AccountController::class);
        Route::resource('contributions', PartnerContributionController::class);
        Route::resource('employee-payments', EmployeePaymentController::class);
        Route::resource('transfers', TransferController::class);
    },
);
