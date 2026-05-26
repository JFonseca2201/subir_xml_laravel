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
        Schema::create('pedido_distribuidor_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos_distribuidor')->onDelete('cascade');
            $table->foreignId('producto_id')->nullable()->constrained('products')->onDelete('set null');
            $table->string('description');
            $table->integer('cantidad')->default(1);
            $table->decimal('precio_compra_estimado', 12, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedido_distribuidor_detalles');
    }
};
