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
        Schema::create('payments_distribution', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('finance_record_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 20);
            $table->timestamps();
            
            // Indexes
            $table->index(['finance_record_id']);
            $table->index(['account_id']);
            $table->index(['finance_record_id', 'account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments_distribution');
    }
};
