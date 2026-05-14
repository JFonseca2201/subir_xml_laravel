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
    Schema::create('financial_movements', function (Blueprint $table) {
        $table->id();
        $table->morphs('movable'); 
        $table->enum('type', ['income', 'expense', 'transfer']);
        $table->decimal('amount', 15, 2);
        $table->string('description');
        $table->date('entry_date');
        $table->foreignId('account_id')->constrained('accounts')->onDelete('cascade');
        $table->json('metadata')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_movements');
    }
};