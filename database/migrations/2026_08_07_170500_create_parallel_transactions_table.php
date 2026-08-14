<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parallel_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type'); // 'income' or 'expense'
            $table->text('description');
            $table->integer('quantity')->nullable();

            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('unit')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('account'); // 'EFECTIVO' or 'TRANSFERENCIA'
            $table->date('date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parallel_transactions');
    }
};
