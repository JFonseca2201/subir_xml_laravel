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
        Schema::table('payment_distributions', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_distributions', 'is_advance')) {
                $table->boolean('is_advance')->default(false)->after('payment_method');
            }
            if (!Schema::hasColumn('payment_distributions', 'work_order_advance_id')) {
                $table->unsignedBigInteger('work_order_advance_id')->nullable()->after('is_advance');
                $table->index('work_order_advance_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_distributions', function (Blueprint $table) {
            if (Schema::hasColumn('payment_distributions', 'work_order_advance_id')) {
                $table->dropIndex(['work_order_advance_id']);
                $table->dropColumn('work_order_advance_id');
            }
            if (Schema::hasColumn('payment_distributions', 'is_advance')) {
                $table->dropColumn('is_advance');
            }
        });
    }
};

