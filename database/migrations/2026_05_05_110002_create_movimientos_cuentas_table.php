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
        Schema::create('movimientos_cuentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('accounts')->onDelete('restrict');
            $table->enum('tipo', ['INGRESO', 'EGRESO']);
            $table->decimal('monto', 12, 2);
            $table->text('descripcion');
            $table->string('referencia'); // ej: 'aporte_capital', 'reverso_aporte'
            $table->unsignedBigInteger('referencia_id')->nullable(); // ID del registro original
            $table->date('fecha');
            $table->timestamps();
            
            // Índices para rendimiento
            $table->index(['cuenta_id', 'fecha']);
            $table->index(['referencia', 'referencia_id']);
            $table->index('tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_cuentas');
    }
};
