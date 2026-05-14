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
        Schema::table('employee_payments', function (Blueprint $table) {
            $table->dropColumn('employee_name');

            // Agregamos las llaves foráneas y columnas que faltaban para vincular el pago correctamente
            $table->foreignId('employee_id')->after('id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('account_id')->after('employee_id')->constrained('accounts')->onDelete('cascade');
            $table->enum('payment_method', ['EFECTIVO', 'TRANSFERENCIA'])->default('EFECTIVO')->after('concept');
            $table->string('reference')->nullable()->after('payment_method');
            $table->enum('type', ['payment'])->default('payment')->after('reference');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_payments', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['account_id']);
            $table->dropForeign(['created_by']);
            $table->dropColumn(['employee_id', 'account_id', 'payment_method', 'reference', 'type', 'created_by']);

            $table->string('employee_name')->nullable()->after('id');
        });
    }
};