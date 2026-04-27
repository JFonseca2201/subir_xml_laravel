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
        Schema::table('daily_cash_flows', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'transfer'])->default('cash')->after('account_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_cash_flows', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
