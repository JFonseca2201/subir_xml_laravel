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
        Schema::create('pedidos_distribuidor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribuidor_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            $table->string('estado')->default('pendiente'); // 'pendiente', 'por_confirmar', 'completado', 'cancelado'
            $table->decimal('total', 12, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos_distribuidor');
    }
};
