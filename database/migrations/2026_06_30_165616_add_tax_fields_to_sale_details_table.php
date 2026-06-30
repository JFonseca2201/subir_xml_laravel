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
        Schema::table('sale_details', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(15.00)->after('discount'); // Porcentaje del IVA, ej: 15.00
            $table->decimal('tax_value', 12, 2)->default(0.00)->after('tax_rate'); // Valor calculado del IVA
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_value']);
        });
    }
};
