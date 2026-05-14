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
        Schema::create('aportes_capital', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->onDelete('restrict');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->decimal('monto', 12, 2);
            $table->text('descripcion');
            $table->foreignId('cuenta_id')->constrained('accounts')->onDelete('restrict');
            $table->enum('metodo_pago', ['EFECTIVO', 'TRANSFERENCIA']);
            $table->date('fecha_aporte');
            $table->time('hora_aporte');
            $table->timestamps();
            $table->softDeletes();
            
            // Índices para rendimiento
            $table->index(['fecha_aporte', 'deleted_at']);
            $table->index('partner_id');
            $table->index('cuenta_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aportes_capital');
    }
};