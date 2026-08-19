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
        Schema::table('quotes', function (Blueprint $table) {
            if (!Schema::hasColumn('quotes', 'converted_work_order_id')) {
                $table->foreignId('converted_work_order_id')
                    ->nullable()
                    ->after('converted_sale_id')
                    ->constrained('work_orders')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (Schema::hasColumn('quotes', 'converted_work_order_id')) {
                $table->dropForeign(['converted_work_order_id']);
                $table->dropColumn('converted_work_order_id');
            }
        });
    }
};
