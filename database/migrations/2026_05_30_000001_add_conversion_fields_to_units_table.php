<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega campos necesarios para el sistema de conversiones de unidades
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            // Campo para el código único de la unidad (ej: UND, PAR, JG4, L, GAL_US, GAL_4L, ML)
            // Primero agregar el campo sin restricción unique
            $table->string('code')->nullable()->after('name');

            // Campo para la categoría de la unidad (count: conteo/empaque, volume: volumen/fluidos)
            $table->enum('category', ['count', 'volume'])->default('count')->after('code');

            // Campo para indicar si es la unidad base de su categoría
            $table->boolean('is_base')->default(false)->after('category');

            // Campo para el factor de conversión a la unidad base
            $table->decimal('factor', 10, 5)->default(1.00)->after('is_base');
        });

        // Actualizar registros existentes para que tengan códigos únicos
        $existingUnits = DB::table('units')->get();
        foreach ($existingUnits as $index => $unit) {
            // Generar código basado en el nombre si está vacío
            if (empty($unit->code)) {
                $code = strtoupper(substr($unit->name, 0, 3)) . ($index + 1);
                DB::table('units')
                    ->where('id', $unit->id)
                    ->update([
                        'code' => $code,
                        'category' => 'count', // Por defecto a count para registros existentes
                        'is_base' => $index === 0, // El primero como base
                        'factor' => 1.00, // Por defecto 1.00
                    ]);
            }
        }

        // Ahora agregar la restricción unique después de actualizar los datos
        Schema::table('units', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'category', 'is_base', 'factor']);
        });
    }
};
