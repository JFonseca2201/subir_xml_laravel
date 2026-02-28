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
        Schema::table('products', function (Blueprint $table) {
            $table->string('code')->unique()->nullable()->after('name');
            $table->decimal('purchase_price', 12, 2)->default(0)->after('price');
            $table->decimal('wholesale_price', 12, 2)->default(0)->after('purchase_price');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('wholesale_price');
            $table->decimal('discount_percentage', 5, 2)->default(0)->after('tax_rate');
            $table->string('barcode')->unique()->nullable()->after('discount_percentage');
            $table->string('sku')->unique()->nullable()->after('barcode');
            $table->string('brand')->nullable()->after('sku');
            $table->string('model')->nullable()->after('brand');
            $table->decimal('weight', 8, 2)->nullable()->after('stock');
            $table->decimal('dimensions_length', 8, 2)->nullable()->after('weight');
            $table->decimal('dimensions_width', 8, 2)->nullable()->after('dimensions_length');
            $table->decimal('dimensions_height', 8, 2)->nullable()->after('dimensions_width');
            $table->string('unit_of_measure')->default('UNIDAD')->after('dimensions_height');
            $table->tinyInteger('item_type')->default(1)->after('unit_of_measure');
            $table->decimal('min_stock', 10, 2)->default(0)->after('item_type');
            $table->decimal('max_stock', 10, 2)->default(0)->after('min_stock');
            $table->boolean('is_taxable')->default(true)->after('max_stock');
            $table->boolean('is_active')->default(true)->after('is_taxable');
            $table->text('notes')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'purchase_price',
                'wholesale_price',
                'tax_rate',
                'discount_percentage',
                'barcode',
                'sku',
                'brand',
                'model',
                'weight',
                'dimensions_length',
                'dimensions_width',
                'dimensions_height',
                'unit_of_measure',
                'item_type',
                'min_stock',
                'max_stock',
                'is_taxable',
                'is_active',
                'notes'
            ]);
        });
    }
};
