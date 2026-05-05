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
            // Agregar la columna work_order_id si no existe
            if (!Schema::hasColumn('transactions', 'work_order_id')) {
                $table->unsignedBigInteger('work_order_id')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Revertir los cambios
            $table->dropColumn('work_order_id');
            $table->foreignId('work_order_id')->nullable()->constrained()->onDelete('set null');
        });
    }
};
