<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Añadir estado 'draft' a sales (MySQL)
        DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('draft', 'pending', 'completed', 'canceled') DEFAULT 'completed'");

        // 2. Añadir estado 'draft' a work_orders (MySQL)
        DB::statement("ALTER TABLE work_orders MODIFY COLUMN status ENUM('draft', 'received', 'in_progress', 'ready', 'delivered') DEFAULT 'received'");

        // 3. Modificar pedidos_distribuidor
        Schema::table('pedidos_distribuidor', function (Blueprint $table) {
            $table->string('number')->nullable()->after('id')->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('pending', 'completed', 'canceled') DEFAULT 'completed'");
        DB::statement("ALTER TABLE work_orders MODIFY COLUMN status ENUM('received', 'in_progress', 'ready', 'delivered') DEFAULT 'received'");

        Schema::table('pedidos_distribuidor', function (Blueprint $table) {
            $table->dropColumn('number');
        });
    }
};
