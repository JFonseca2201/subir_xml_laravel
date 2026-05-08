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
        Schema::create('finance_records', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date')->default(now('America/Guayaquil')->toDateString());
            $table->tinyInteger('type')->default(0)->comment('0: Income, 1: Expense');
            $table->tinyInteger('account_id')->default(1)->comment('1: Cash, 2: Pichincha, 3: Guayaquil');
            $table->string('payment_method', 20)->default('cash')->comment('cash or transfer');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('work_order_number')->nullable();
            $table->string('invoice_number')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Indexes
            $table->index('entry_date');
            $table->index('type');
            $table->index('account_id');
            $table->index('user_id');
            $table->index(['entry_date', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_records');
    }
};
