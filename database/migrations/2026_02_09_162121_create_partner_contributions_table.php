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
        Schema::create('partner_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('contribution_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_contributions');
    }
};
