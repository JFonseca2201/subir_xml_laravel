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
        Schema::create('product_return_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_return_id')->constrained('product_returns')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('products');
            $table->string('description'); // In case product_id is null
            $table->integer('quantity');
            $table->decimal('price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_return_details');
    }
};
