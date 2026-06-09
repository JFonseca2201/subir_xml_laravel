<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_cash_counts', function (Blueprint $table) {
            $table->id();
            $table->date('count_date')->unique(); // Una sola fila por día obligatoria

            // Totales por cuenta (Campos dinámicos o fijos según tu catálogo)
            $table->decimal('cash_total', 12, 2)->default(0.00);      // Total Efectivo (Caja)
            $table->decimal('pichincha_total', 12, 2)->default(0.00); // Banco Pichincha
            $table->decimal('guayaquil_total', 12, 2)->default(0.00); // Banco Guayaquil
            $table->decimal('grand_total', 12, 2)->default(0.00);     // Suma de todo lo anterior

            // JSON para el desglose físico de billetes y monedas (El casillero)
            $table->json('cash_details')->nullable();

            $table->text('observations')->nullable();
            $table->foreignId('user_id')->constrained('users'); // Quién contó el dinero
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_cash_counts');
    }
};
