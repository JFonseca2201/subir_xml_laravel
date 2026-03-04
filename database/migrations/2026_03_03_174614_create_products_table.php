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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('description')->comment('Nombre del producto');
            $table->string('sku')->unique()->nullable()->comment('SKU del producto');
            $table->string('imagen')->nullable()->comment('URL de la imagen');
            $table->string('code_aux')->nullable()->comment('Código auxiliar');
            $table->string('uses')->nullable()->comment('Usos del producto');
            $table->foreignId('product_categorie_id')->nullable()->constrained('product_categories')->onDelete('set null')->comment('Categoría del producto');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null')->comment('Almacén del producto');
            $table->foreignId('unit_id')->nullable()->constrained('units')->onDelete('set null')->comment('Unidad del producto');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('set null')->comment('Proveedor del producto');
            $table->decimal('price', 12, 2)->default(0)->comment('Precio base');
            $table->decimal('price_sale', 12, 2)->default(0)->comment('Precio de venta');
            $table->decimal('purchase_price', 12, 2)->default(0)->comment('Precio de compra');
            $table->decimal('tax_rate', 5, 2)->default(0)->comment('Tasa de impuesto');
            $table->decimal('max_discount', 12, 2)->default(0)->comment('Descuento máximo');
            $table->decimal('discount_percentage', 5, 2)->default(0)->comment('Porcentaje de descuento');
            $table->string('brand')->nullable()->comment('Marca del producto');
            $table->decimal('stock', 10, 2)->default(0)->comment('Stock actual');
            $table->tinyInteger('item_type')->default(1)->comment('Tipo de ítem');
            $table->decimal('min_stock', 10, 2)->default(0)->comment('Stock mínimo');
            $table->decimal('max_stock', 10, 2)->default(0)->comment('Stock máximo');
            $table->boolean('is_taxable')->default(true)->comment('Si es gravable');
            $table->boolean('is_gift')->default(false)->comment('Si es regalo');
            $table->text('notes')->nullable()->comment('Notas del producto');
            $table->integer('state')->default(1)->comment('Estado: 1=Activo, 2=Inactivo');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
