<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Limpia las columnas que quedaron pendientes de una migración fallida
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'code')) {
                $table->dropColumn('code');
            }
            if (Schema::hasColumn('units', 'category')) {
                $table->dropColumn('category');
            }
            if (Schema::hasColumn('units', 'is_base')) {
                $table->dropColumn('is_base');
            }
            if (Schema::hasColumn('units', 'factor')) {
                $table->dropColumn('factor');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No se necesita rollback ya que esta migración solo limpia
    }
};
