<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Cuando una cotización se convierte en venta/factura, guarda el ID de la nueva venta
            // Si este campo NO es null, la cotización está BLOQUEADA
            $table->unsignedBigInteger('converted_to_sale_id')->nullable()->after('work_order_id');
            $table->foreign('converted_to_sale_id')->references('id')->on('sales')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['converted_to_sale_id']);
            $table->dropColumn('converted_to_sale_id');
        });
    }
};
