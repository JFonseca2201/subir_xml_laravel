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
        if (!Schema::hasTable('partner_contributions')) {
            Schema::create('partner_contributions', function (Blueprint $table) {
                $table->id();
                
                $table->foreignId('partner_id')->constrained()->onDelete('cascade');
                $table->foreignId('account_id')->nullable()->constrained()->onDelete('set null');
                
                $table->decimal('amount', 15, 2);
                $table->date('contribution_date');
                $table->text('notes')->nullable();
                
                $table->timestamps();
                
                $table->index(['partner_id', 'contribution_date']);
                $table->index(['account_id', 'contribution_date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_contributions');
    }
};
