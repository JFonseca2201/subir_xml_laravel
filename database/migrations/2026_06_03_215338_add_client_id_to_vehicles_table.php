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
        // 1. Añadir la columna 'client_id' permitiendo valores NULL temporalmente.
        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->after('user_id');
        });

        // 2. Hacer un UPDATE masivo en la tabla 'vehicles' para asignar el cliente "Consumidor Final"
        // Buscamos el ID del cliente "Consumidor Final" por su nombre en la tabla 'clients'.
        $consumidorFinalId = DB::table('clients')
            ->where('full_name', 'like', '%Consumidor Final%')
            ->value('id');

        // Si no se encuentra, usamos el ID por defecto (puedes cambiar este ID si es necesario)
        if (!$consumidorFinalId) {
            $consumidorFinalId = 1; // ID del cliente "Consumidor Final"
        }

        DB::table('vehicles')->whereNull('client_id')->update([
            'client_id' => $consumidorFinalId
        ]);

        // 3 y 4. Modificar la columna para pasar a NOT NULL y añadir la Llave Foránea apuntando a 'clients(id)' con restricción ON DELETE RESTRICT.
        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable(false)->change();
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('restrict');
            
            $table->index(['client_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });
    }
};
