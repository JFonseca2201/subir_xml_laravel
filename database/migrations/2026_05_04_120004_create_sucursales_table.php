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
        if (!Schema::hasTable('sucursales')) {
            Schema::create('sucursales', function (Blueprint $table) {
                $table->id();

                $table->string('name');
                $table->text('address')->nullable();
                $table->string('ruc')->nullable();
                $table->string('trade_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('secuencial_factura')->nullable();
                $table->string('serie_factura')->nullable();
                $table->string('establecimiento')->nullable();
                $table->string('punto_emision')->nullable();
                $table->string('ambiente')->nullable();
                $table->string('tipo_emision')->nullable();
                $table->string('firma_electronica')->nullable();
                $table->string('password_firma')->nullable();
                $table->string('logo')->nullable();
                $table->string('obligado_contabilidad')->nullable();
                $table->string('contribuyente_especial')->nullable();
                $table->string('status')->nullable();

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};
