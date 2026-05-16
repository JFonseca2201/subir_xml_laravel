<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_details', function (Blueprint $table) {
            $table->id();
            
            // Llave foránea hacia la cabecera (Venta)
            $table->foreignId('sale_id')->constrained('sales')->onDelete('cascade');
            
            // Llave foránea hacia tus productos/repuestos (nullable si se borra del catálogo)
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null');
            
            // Datos históricos del ítem vendido
            $table->string('description'); // Ej: Llanta Tornel, Alineación, etc.
            $table->integer('quantity');
            $table->decimal('price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0.00);
            $table->decimal('total', 12, 2); // (cantidad * precio) - descuento
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_details');
    }
};