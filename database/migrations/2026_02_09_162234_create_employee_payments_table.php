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
        Schema::create('employee_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('amount', 15, 2);
            $table->date('payment_date')->comment('Fecha del pago');
            $table->text('concept')->nullable()->comment('Concepto del pago');
            $table->enum('payment_type', ['cash', 'transfer', 'check', 'deposit'])->default('cash')->comment('Tipo de pago');
            $table->boolean('state')->default(true)->comment('true=Activo, false=Inactivo');
            $table->foreignId('sucursale_id')->nullable()->constrained('sucursales')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('employee_id');
            $table->index('payment_date');
            $table->index('payment_type');
            $table->index('state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_payments');
    }
};
