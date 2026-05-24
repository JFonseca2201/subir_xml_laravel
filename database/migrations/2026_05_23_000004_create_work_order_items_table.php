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
        Schema::create('work_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null');
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->string('type')->default('product'); // 'product' or 'service'
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_items');
    }
};
