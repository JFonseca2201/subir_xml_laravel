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
        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->onDelete('cascade');
            $table->enum('type', ['income', 'expense']);
            $table->enum('category', ['contribution', 'salary_payment', 'salary_advance', 'transfer', 'expense_general', 'sale', 'purchase']);
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable()->comment('ID de referencia a modelo relacionado');
            $table->string('reference_type')->nullable()->comment('Modelo de referencia: sale, purchase, etc.');
            $table->uuid('transfer_group_id')->nullable()->comment('UUID para agrupar transferencias');
            $table->date('transaction_date')->comment('Fecha de la transacción');
            $table->timestamps();

            // Índices
            $table->index(['account_id', 'transaction_date']);
            $table->index(['type', 'category']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('transfer_group_id');
            $table->index('amount');
            $table->index('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_transactions');
    }
};
