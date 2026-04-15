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
            $table->enum('category', ['contribution', 'salary_payment', 'salary_advance', 'transfer', 'expense_general']);
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->uuid('transfer_group_id')->nullable();
            $table->date('transaction_date');
            $table->timestamps();

            // Índices
            $table->index(['account_id', 'transaction_date']);
            $table->index(['type', 'category']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('transfer_group_id');
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
