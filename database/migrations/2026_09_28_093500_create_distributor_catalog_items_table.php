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
        Schema::create('distributor_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->string('supplier_name', 150)->nullable()->index();
            $table->string('code', 100)->index();
            $table->text('description');
            $table->string('reference', 100)->nullable()->index();
            $table->decimal('price', 12, 2)->default(0.00);
            $table->string('category_code', 50)->nullable()->index();
            $table->string('category_name', 255)->index();
            $table->string('stock_status', 100)->nullable()->default('Disponible')->index();
            $table->timestamps();

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->onDelete('cascade');

            $table->index(['supplier_id', 'code']);
            $table->index(['supplier_id', 'category_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distributor_catalog_items');
    }
};
