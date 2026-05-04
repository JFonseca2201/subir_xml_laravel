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
            
            $table->string('employee_name');
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('concept')->nullable();
            
            $table->timestamps();
            
            $table->index(['payment_date', 'employee_name']);
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
