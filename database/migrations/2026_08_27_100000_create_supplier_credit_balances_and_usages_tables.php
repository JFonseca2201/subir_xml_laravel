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
        if (!Schema::hasTable('supplier_credit_balances')) {
            Schema::create('supplier_credit_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
                $table->foreignId('account_id')->nullable()->constrained('accounts')->onDelete('set null');
                $table->foreignId('finance_record_id')->nullable()->constrained('finance_records')->onDelete('set null');
                
                // Tipo de origen: pago excedente, nota de crédito recibida, ajuste manual
                $table->enum('source_type', ['overpayment', 'credit_note', 'manual_adjustment'])->default('overpayment');
                
                // Número de comprobante o referencia (ej. número de NC, número de transferencia)
                $table->string('reference_number')->nullable();
                
                // Montos de la operación de conciliación
                $table->decimal('total_payment_amount', 12, 2)->default(0.00); // Monto pagado en total (ej. $179.12)
                $table->decimal('invoices_total_amount', 12, 2)->default(0.00); // Total facturas liquidadas (ej. $171.42)
                
                // Saldo a favor
                $table->decimal('amount', 12, 2)->default(0.00); // Diferencia a favor inicial (ej. $7.70)
                $table->decimal('used_amount', 12, 2)->default(0.00); // Monto ya utilizado en compras posteriores
                $table->decimal('remaining_balance', 12, 2)->default(0.00); // Saldo disponible
                
                // Estado del saldo a favor / NC
                $table->enum('status', ['available', 'partially_used', 'fully_used', 'refunded', 'canceled'])->default('available');
                
                // Opción de resolución aplicada en la conciliación
                // credit_balance (guardado para próximas compras), credit_note (NC registrada), immediate_refund (reembolsado a cuenta)
                $table->enum('resolution_type', ['credit_balance', 'credit_note', 'immediate_refund'])->default('credit_balance');
                
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('supplier_credit_usages')) {
            Schema::create('supplier_credit_usages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_credit_balance_id')->constrained('supplier_credit_balances')->onDelete('cascade');
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->onDelete('set null');
                $table->decimal('amount_applied', 12, 2);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_credit_usages');
        Schema::dropIfExists('supplier_credit_balances');
    }
};
