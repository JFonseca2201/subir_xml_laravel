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
        Schema::create('account_payables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->enum('status', ['pending', 'partial', 'paid'])->default('pending');
            $table->date('due_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_payables');
    }
};
