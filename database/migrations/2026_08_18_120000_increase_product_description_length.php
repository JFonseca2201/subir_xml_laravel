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
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->text('description')->change();
            });
        }

        if (Schema::hasTable('sale_details')) {
            Schema::table('sale_details', function (Blueprint $table) {
                $table->text('description')->change();
            });
        }

        if (Schema::hasTable('invoice_items')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->text('description')->change();
            });
        }

        if (Schema::hasTable('quote_details')) {
            Schema::table('quote_details', function (Blueprint $table) {
                $table->text('description')->change();
            });
        }

        if (Schema::hasTable('work_order_items')) {
            Schema::table('work_order_items', function (Blueprint $table) {
                $table->text('description')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('description', 255)->change();
            });
        }
    }
};
