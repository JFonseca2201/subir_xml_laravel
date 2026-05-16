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
        // Drop old column if it exists
        if (Schema::hasColumn('employee_payments', 'concept')) {
            Schema::table('employee_payments', function (Blueprint $table) {
                $table->dropColumn('concept');
            });
        }

        Schema::table('employee_payments', function (Blueprint $table) {
            // Add new columns
            if (!Schema::hasColumn('employee_payments', 'description')) {
                $table->text('description')->nullable()->after('amount');
            }
            
            // Update amount column to match controller expectations
            $table->decimal('amount', 10, 2)->change();
            
            // Add indexes
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
            if (Schema::hasColumn('employee_payments', 'description')) {
                $table->dropColumn('description');
            }
            
            // Revert amount column
            $table->decimal('amount', 15, 2)->change();
            
            // Drop indexes
            $table->dropIndex(['payment_date', 'type']);
            $table->dropIndex(['payment_date']);
            $table->dropIndex(['type']);
        });

        // Add old column back if it doesn't exist
        if (!Schema::hasColumn('employee_payments', 'concept')) {
            Schema::table('employee_payments', function (Blueprint $table) {
                $table->string('concept')->nullable()->after('amount');
            });
        }
    }
};