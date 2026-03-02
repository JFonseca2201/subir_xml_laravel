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
            $table->string('description')->nullable()->after('name');
            $table->string('code')->unique()->nullable()->after('description');
            $table->string('imagen')->nullable()->after('code');
            $table->string('code_aux')->nullable()->after('imagen');
            $table->string('uses')->nullable()->after('code_aux');
            $table->foreignId('warehose_id')->nullable()->constrained('warehouses')->onDelete('set null')->after('categorie_id');
            $table->foreignId('unit_id')->nullable()->constrained('units')->onDelete('set null')->after('warehose_id');
            $table->decimal('purchase_price', 12, 2)->default(0)->after('price');
            $table->decimal('wholesale_price', 12, 2)->default(0)->after('purchase_price');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('wholesale_price');
            $table->decimal('discount_percentage', 5, 2)->default(0)->after('tax_rate');
            $table->string('barcode')->unique()->nullable()->after('discount_percentage');
            $table->string('sku')->unique()->nullable()->after('barcode');
            $table->string('brand')->nullable()->after('sku');
            $table->decimal('stock', 10, 2)->default(0)->after('brand');
            $table->tinyInteger('item_type')->default(1)->after('stock');
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
                'description',
                'code',
                'imagen',
                'code_aux',
                'uses',
                'warehose_id',
                'unit_id',
                'purchase_price',
                'wholesale_price',
                'tax_rate',
                'discount_percentage',
                'barcode',
                'sku',
                'brand',
                'stock',
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
