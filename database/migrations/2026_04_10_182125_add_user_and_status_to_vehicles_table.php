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
        Schema::table('vehicles', function (Blueprint $table) {
            // Relación con el usuario que lo ingresó
            // set null: Si el usuario se borra, el registro del vehículo persiste (importante para historial)
            $table->foreignId('user_id')
                ->nullable()
                ->after('id') // Lo ponemos al principio para orden
                ->constrained('users')
                ->onDelete('set null');

            // Estado del vehículo
            // 1: Activo (Predeterminado)
            // 2: Inactivo
            $table->tinyInteger('status')
                ->default(1)
                ->after('description')
                ->comment('1: Activo, 2: Inactivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Primero eliminamos la clave foránea y luego la columna
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'status']);
        });
    }
};
