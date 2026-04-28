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
        Schema::create('cash_balance_operations', function (Blueprint $table) {
            $table->id();
            $table->enum('operation_type', ['opening', 'closing']); // apertura o cierre de caja
            $table->date('operation_date');
            $table->foreignId('account_id')->constrained('accounts')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Balance del sistema al momento de la operación
            $table->decimal('system_balance', 15, 2)->default(0);
            $table->decimal('expected_balance', 15, 2)->default(0);
            $table->decimal('actual_balance', 15, 2)->default(0);
            $table->decimal('difference', 15, 2)->default(0);

            // Referencia al conteo de denominaciones (si aplica)
            $table->foreignId('cash_denomination_id')->nullable()->constrained('cash_denominations')->onDelete('set null');

            // Estado de la operación
            $table->enum('status', ['pending', 'completed', 'reconciled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['operation_type', 'operation_date']);
            $table->index('account_id');
            $table->index('operation_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_balance_operations');
    }
};
