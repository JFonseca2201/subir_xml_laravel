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
        Schema::table('account_transactions', function (Blueprint $table) {
            $table->index(['reference_type', 'reference_id'], 'account_transactions_ref_type_ref_id_index');
            $table->index(['account_id', 'transaction_date'], 'account_transactions_account_date_index');
            $table->index('transaction_date', 'account_transactions_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            //
        });
    }
};
