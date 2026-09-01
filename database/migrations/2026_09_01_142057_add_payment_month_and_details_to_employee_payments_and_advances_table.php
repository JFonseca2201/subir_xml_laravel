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
            if (!Schema::hasColumn('employee_payments', 'payment_month')) {
                $table->string('payment_month', 7)->nullable()->after('payment_date')->comment('Mes y año del pago ej: 2026-09');
            }
            if (!Schema::hasColumn('employee_payments', 'base_salary')) {
                $table->decimal('base_salary', 10, 2)->default(0)->after('amount')->comment('Sueldo base pactado');
            }
            if (!Schema::hasColumn('employee_payments', 'advances_amount')) {
                $table->decimal('advances_amount', 10, 2)->default(0)->after('base_salary')->comment('Total adelantos descontados');
            }
            if (!Schema::hasColumn('employee_payments', 'net_amount')) {
                $table->decimal('net_amount', 10, 2)->default(0)->after('advances_amount')->comment('Monto neto pagado');
            }
        });

        Schema::table('employee_advances', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_advances', 'employee_payment_id')) {
                $table->unsignedBigInteger('employee_payment_id')->nullable()->after('employee_id')->comment('ID del pago/rol donde fue deducido');
                $table->foreign('employee_payment_id')->references('id')->on('employee_payments')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_advances', function (Blueprint $table) {
            if (Schema::hasColumn('employee_advances', 'employee_payment_id')) {
                $table->dropForeign(['employee_payment_id']);
                $table->dropColumn('employee_payment_id');
            }
        });

        Schema::table('employee_payments', function (Blueprint $table) {
            $cols = ['payment_month', 'base_salary', 'advances_amount', 'net_amount'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('employee_payments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
