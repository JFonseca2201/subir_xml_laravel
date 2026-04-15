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
        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('amount', 15, 2);
            $table->date('advance_date');
            $table->text('concept')->nullable();
            $table->tinyInteger('state')->default(1)->comment('1=Pendiente, 2=Descontado, 3=Anulado');
            $table->foreignId('employee_payment_id')->nullable()->constrained('employee_payments')->onDelete('set null');
            $table->foreignId('sucursale_id')->nullable()->default(1)->constrained('sucursales')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_advances');
    }
};
