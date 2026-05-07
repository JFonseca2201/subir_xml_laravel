<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('employee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('account_id')->constrained('accounts')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->text('description')->nullable();
            $table->date('payment_date');
            $table->enum('payment_method', ['EFECTIVO', 'TRANSFERENCIA']);
            $table->string('reference')->nullable();
            $table->enum('type', ['payment']);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('employee_payments');
    }
};
