<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration 
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nombre Comercial o Razon Social corto
            $table->string('address'); // Direccion Matriz
            $table->string('ruc', 13)->unique();
            $table->string('trade_name')->nullable(); // Nombre Comercial
            $table->string('secuencial_factura')->default('000000001');
            $table->string('serie_factura')->default('001-001'); // Estb-PtoEmi
            $table->string('establecimiento', 3)->default('001');
            $table->string('punto_emision', 3)->default('001');
            $table->integer('ambiente')->default(1); // 1: Pruebas, 2: Produccion
            $table->integer('tipo_emision')->default(1); // 1: Normal
            $table->string('firma_electronica')->nullable(); // Path .p12
            $table->string('password_firma')->nullable();
            $table->string('logo')->nullable();
            $table->enum('obligado_contabilidad', ['SI', 'NO'])->default('NO');
            $table->string('contribuyente_especial')->nullable(); // Resolution number
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};
