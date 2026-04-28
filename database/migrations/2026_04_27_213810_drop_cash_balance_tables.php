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
        // Eliminar tablas antiguas
        Schema::dropIfExists('cash_balance_operations');
        Schema::dropIfExists('cash_denominations');

        // Crear nueva tabla cash_sessions
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('ID del usuario que abre la caja');
            $table->decimal('opening_balance', 15, 2)->default(0)->comment('Saldo inicial al abrir caja');
            $table->decimal('cash_deposits', 15, 2)->default(0)->comment('Total de depósitos manuales');
            $table->decimal('cash_withdrawals', 15, 2)->default(0)->comment('Total de retiros manuales');
            $table->decimal('sales_total', 15, 2)->default(0)->comment('Total de ventas al contado');
            $table->enum('status', ['open', 'closed'])->default('open')->comment('Estado de la sesión');
            $table->decimal('final_balance', 15, 2)->nullable()->comment('Saldo final al cerrar');
            $table->timestamp('closed_at')->nullable()->comment('Fecha y hora de cierre');
            $table->timestamps();

            // Índices
            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index('created_at');
            $table->index('closed_at');
        });

        // Crear nueva tabla cash_denominations para arqueo físico
        Schema::create('cash_denominations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained('cash_sessions')->onDelete('cascade')->comment('ID de la sesión de caja');

            // Billetes
            $table->integer('bill_100_count')->default(0)->comment('Cantidad de billetes de $100');
            $table->integer('bill_50_count')->default(0)->comment('Cantidad de billetes de $50');
            $table->integer('bill_20_count')->default(0)->comment('Cantidad de billetes de $20');
            $table->integer('bill_10_count')->default(0)->comment('Cantidad de billetes de $10');
            $table->integer('bill_5_count')->default(0)->comment('Cantidad de billetes de $5');
            $table->integer('bill_1_count')->default(0)->comment('Cantidad de billetes de $1');

            // Monedas
            $table->integer('coin_1_count')->default(0)->comment('Cantidad de monedas de $1.00');
            $table->integer('coin_50_count')->default(0)->comment('Cantidad de monedas de $0.50');
            $table->integer('coin_25_count')->default(0)->comment('Cantidad de monedas de $0.25');
            $table->integer('coin_10_count')->default(0)->comment('Cantidad de monedas de $0.10');
            $table->integer('coin_5_count')->default(0)->comment('Cantidad de monedas de $0.05');
            $table->integer('coin_1_cent_count')->default(0)->comment('Cantidad de monedas de $0.01');

            // Totales
            $table->decimal('total_physical', 15, 2)->default(0)->comment('Total físico contado');
            $table->decimal('expected_balance', 15, 2)->default(0)->comment('Balance esperado según sistema');
            $table->decimal('difference', 15, 2)->default(0)->comment('Diferencia entre físico y esperado');

            $table->timestamps();

            // Índices
            $table->index('cash_session_id');
            $table->index('total_physical');
            $table->index('difference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_denominations');
        Schema::dropIfExists('cash_sessions');
    }
};
