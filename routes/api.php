<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Roles\RoleController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Invoice\InvoiceXmlImportController;

Route::group([
    /* 'middleware' => 'api', */
    'prefix' => 'auth',
    /* 'middleware' => ['auth:api', 'permission:publish articles'], */
], function ($router) {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api')->name('logout');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:api')->name('refresh');
    Route::post('/me', [AuthController::class, 'me'])->middleware('auth:api')->name('me');
});

Route::group([
    'middleware' => ['auth:api'],
], function () {
    Route::resource('role', RoleController::class);
});


Route::post('invoices/import-xml', [InvoiceXmlImportController::class, 'store']);
Route::post('invoices/index', [InvoiceXmlImportController::class, 'index']);
Route::get('invoices/config', [InvoiceXmlImportController::class, 'config']);
Route::get('/invoices/{id}', [InvoiceXmlImportController::class, 'show']);