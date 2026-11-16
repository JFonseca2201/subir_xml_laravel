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
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'inventoried_at')) {
            Schema::table('products', function (Blueprint $table) {
                $table->timestamp('inventoried_at')->nullable()->default(null)->after('state');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'inventoried_at')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('inventoried_at');
            });
        }
    }
};
