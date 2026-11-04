<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

/**
 * Servir archivos de storage de forma directa y compatible con Windows / php artisan serve.
 * En Windows, php -S no sigue symlinks NTFS automáticamente en public/storage,
 * provocando que las peticiones caigan en el enrutador de Laravel.
 */
Route::get('storage/{path}', function ($path) {
    if (!Storage::disk('public')->exists($path)) {
        abort(404, 'Archivo no encontrado');
    }

    $fullPath = Storage::disk('public')->path($path);
    $mime = Storage::disk('public')->mimeType($path) ?: 'application/pdf';

    return response()->file($fullPath, [
        'Content-Type'        => $mime,
        'Content-Disposition' => 'inline',
    ]);
})->where('path', '.*');
