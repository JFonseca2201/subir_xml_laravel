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

            $table->string('code')->unique(); // código interno del sistema
            $table->string('name');
            $table->enum('type', ['cash', 'bank']);
            $table->string('bank_name')->nullable();
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->boolean('state')->default(true); // true = activo, false = inactivo
            $table->boolean('is_system')->default(false); // false = creado por usuario, true = sistema

            $table->timestamps();

            // Índices
            $table->index('code');
            $table->index('type');
            $table->index('state');
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
