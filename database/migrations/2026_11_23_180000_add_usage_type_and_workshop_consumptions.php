<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Agregar usage_type a products
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'usage_type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('usage_type', 35)->default('commercial_product')->after('item_type')->index();
            });

            // Sincronizar registros existentes
            DB::table('products')->where('item_type', 2)->update(['usage_type' => 'service']);
            DB::table('products')->where('item_type', 1)->update(['usage_type' => 'commercial_product']);
        }

        // 2. Agregar usage_type a invoice_items
        if (Schema::hasTable('invoice_items') && !Schema::hasColumn('invoice_items', 'usage_type')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->string('usage_type', 35)->default('commercial_product')->after('item_type')->index();
            });

            DB::table('invoice_items')->where('item_type', 1)->update(['usage_type' => 'commercial_product']);
            DB::table('invoice_items')->where('item_type', 2)->update(['usage_type' => 'workshop_consumable']);
            DB::table('invoice_items')->where('item_type', 3)->update(['usage_type' => 'workshop_stock_unit']);
        }

        // 3. Crear tabla workshop_consumptions para registrar salidas por consumo interno
        if (!Schema::hasTable('workshop_consumptions')) {
            Schema::create('workshop_consumptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('quantity', 10, 2)->default(1.00); // Número de envases / cajas abiertas
                $table->decimal('unit_cost', 12, 2)->default(0.00); // Costo por envase al momento de abrir
                $table->decimal('total_cost', 12, 2)->default(0.00); // quantity * unit_cost
                $table->string('station_or_notes', 255)->nullable(); // Estación de trabajo / Notas
                $table->timestamp('consumed_at')->useCurrent();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_consumptions');

        if (Schema::hasTable('invoice_items') && Schema::hasColumn('invoice_items', 'usage_type')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->dropColumn('usage_type');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'usage_type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('usage_type');
            });
        }
    }
};
