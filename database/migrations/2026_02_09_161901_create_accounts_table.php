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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique(); // 🔥 código interno del sistema

            $table->string('name');
            $table->enum('type', ['cash', 'bank']);
            $table->string('bank_name')->nullable();

            $table->decimal('initial_balance', 15, 2)->default(0);

            $table->integer('state')->default(1); // 1 es activo, 2 es inactivo
            $table->boolean('is_system')->default(true); // 1 es verdadero, 2 es falso

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
