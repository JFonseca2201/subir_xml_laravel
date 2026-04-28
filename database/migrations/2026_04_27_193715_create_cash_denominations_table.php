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
        Schema::create('cash_denominations', function (Blueprint $table) {
            $table->id();
            $table->enum('operation_type', ['opening', 'closing']); // apertura o cierre de caja
            $table->date('operation_date');
            $table->foreignId('account_id')->constrained('accounts')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Billetes
            $table->integer('bill_100_count')->default(0);
            $table->integer('bill_50_count')->default(0);
            $table->integer('bill_20_count')->default(0);
            $table->integer('bill_10_count')->default(0);
            $table->integer('bill_5_count')->default(0);
            $table->integer('bill_1_count')->default(0);

            // Monedas
            $table->integer('coin_1_count')->default(0); // $1.00
            $table->integer('coin_50_count')->default(0); // $0.50
            $table->integer('coin_25_count')->default(0); // $0.25
            $table->integer('coin_10_count')->default(0); // $0.10
            $table->integer('coin_5_count')->default(0); // $0.05
            $table->integer('coin_1_cent_count')->default(0); // $0.01

            // Totales calculados
            $table->decimal('total_bills', 15, 2)->default(0);
            $table->decimal('total_coins', 15, 2)->default(0);
            $table->decimal('total_cash', 15, 2)->default(0);

            // Balance del sistema
            $table->decimal('system_balance', 15, 2)->default(0);
            $table->decimal('difference', 15, 2)->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['operation_type', 'operation_date']);
            $table->index('account_id');
            $table->index('operation_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_denominations');
    }
};
