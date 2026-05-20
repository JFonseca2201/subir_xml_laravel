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
        Schema::table('employee_payments', function (Blueprint $table) {
            // Drop old column
            $table->dropColumn('concept');
            
            // Add new columns
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade')->after('id');
            $table->foreignId('account_id')->constrained('accounts')->onDelete('cascade')->after('employee_id');
            $table->text('description')->nullable()->after('amount');
            $table->enum('payment_method', ['EFECTIVO', 'TRANSFERENCIA'])->after('payment_date');
            $table->string('reference')->nullable()->after('payment_method');
            $table->enum('type', ['payment'])->default('payment')->after('reference');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->after('type');
            
            // Update amount column to match controller expectations
            $table->decimal('amount', 10, 2)->change();
            
            // Add indexes
            $table->index('employee_id');
            $table->index('account_id');
            $table->index('payment_date');
            $table->index('type');
            $table->index(['payment_date', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_payments', function (Blueprint $table) {
            // Drop new columns
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['account_id']);
            $table->dropForeign(['created_by']);
            $table->dropColumn(['employee_id', 'account_id', 'description', 'payment_method', 'reference', 'type', 'created_by']);
            $table->dropSoftDeletes();
            
            // Add old columns back
            $table->string('employee_name')->after('id');
            $table->string('concept')->nullable()->after('amount');
            
            // Revert amount column
            $table->decimal('amount', 15, 2)->change();
            
            // Drop old index and add new one
            $table->dropIndex(['payment_date', 'type']);
            $table->dropIndex(['payment_date']);
            $table->dropIndex(['type']);
            $table->dropIndex(['account_id']);
            $table->dropIndex(['employee_id']);
            $table->index(['payment_date', 'employee_name']);
        });
    }
};
