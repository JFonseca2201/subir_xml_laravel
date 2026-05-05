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
        Schema::table('transactions', function (Blueprint $table) {
            // Cambiar type de enum a tinyInteger
            if (Schema::hasColumn('transactions', 'type')) {
                $table->dropColumn('type');
            }
            $table->tinyInteger('type')->comment('1: Income, 2: Expense, 3: Transfer');
            
            // Eliminar concept si existe y description no existe
            if (Schema::hasColumn('transactions', 'concept') && !Schema::hasColumn('transactions', 'description')) {
                $table->dropColumn('concept');
                $table->string('description');
            } elseif (!Schema::hasColumn('transactions', 'description')) {
                $table->string('description');
            }
            
            // Eliminar columnas que no se usan
            if (Schema::hasColumn('transactions', 'transactionable_type')) {
                $table->dropMorphs('transactionable');
            }
            if (Schema::hasColumn('transactions', 'transaction_date')) {
                $table->dropColumn('transaction_date');
            }
            
            // Agregar columnas necesarias si no existen
            if (!Schema::hasColumn('transactions', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            }
            if (!Schema::hasColumn('transactions', 'partner_id')) {
                $table->foreignId('partner_id')->nullable()->constrained()->onDelete('set null');
            }
            if (!Schema::hasColumn('transactions', 'employee_id')) {
                $table->foreignId('employee_id')->nullable()->constrained()->onDelete('set null');
            }
            if (!Schema::hasColumn('transactions', 'work_order_id')) {
                $table->unsignedBigInteger('work_order_id')->nullable();
            }
            if (!Schema::hasColumn('transactions', 'invoice_number')) {
                $table->string('invoice_number')->nullable();
            }
            if (!Schema::hasColumn('transactions', 'transfer_group_id')) {
                $table->string('transfer_group_id')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Revertir cambios
            $table->dropColumn('type');
            $table->enum('type', ['income', 'expense']);
            
            $table->dropColumn('description');
            $table->string('concept');
            
            $table->morphs('transactionable');
            $table->timestamp('transaction_date');
            
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            
            $table->dropForeign(['partner_id']);
            $table->dropColumn('partner_id');
            
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
            
            $table->dropColumn('work_order_id');
            $table->dropColumn('invoice_number');
            $table->dropColumn('transfer_group_id');
        });
    }
};
