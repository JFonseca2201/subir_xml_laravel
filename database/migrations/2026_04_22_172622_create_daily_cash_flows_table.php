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
        Schema::create('daily_cash_flows', function (Blueprint $table) {
            $table->id();
            $table->enum('flow_type', ['income', 'expense']);
            $table->date('flow_date');
            $table->string('order_number')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->decimal('total_amount', 15, 2);
            $table->enum('payment_status', ['complete', 'partial', 'pending'])->default('pending');
            $table->text('description')->nullable();
            $table->enum('account_type', [1, 2, 3]); // 1=caja chica, 2=caja, 3=bancos
            $table->foreignId('account_id')->nullable()->constrained('accounts')->onDelete('set null');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('source_type', ['sale', 'purchase', 'other'])->default('other');
            $table->unsignedBigInteger('source_id')->nullable(); // Para futura referencia a ventas/compras
            $table->timestamps();

            // Índices
            $table->index(['flow_date', 'flow_type']);
            $table->index('account_type');
            $table->index('user_id');
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_cash_flows');
    }
};
