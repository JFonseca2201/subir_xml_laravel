<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega el campo factor para conversiones entre unidades
     */
    public function up(): void
    {
        Schema::table('unit_conversions', function (Blueprint $table) {
            // Campo para el factor de conversión entre unidades
            $table->decimal('factor', 10, 5)->default(1.00)->after('unit_to_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_conversions', function (Blueprint $table) {
            $table->dropColumn(['factor']);
        });
    }
};
