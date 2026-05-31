<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Config\Unit;

/**
 * Seeder para poblar las unidades de medida del sistema
 * 
 * Este seeder crea las unidades base para el sistema de gestión de taller mecánico:
 * - Unidades de Conteo/Empaque (Categoría: count)
 * - Unidades de Volumen/Fluidos (Categoría: volume)
 */
class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Unidades de Conteo/Empaque (Categoría: count)
        $countUnits = [
            [
                'name' => 'Unidad',
                'code' => 'UND',
                'description' => 'Unidad base para conteo de productos',
                'category' => 'count',
                'is_base' => true,
                'factor' => 1.00,
                'state' => 1,
            ],
            [
                'name' => 'Par',
                'code' => 'PAR',
                'description' => 'Equivalente a 2 unidades',
                'category' => 'count',
                'is_base' => false,
                'factor' => 2.00,
                'state' => 1,
            ],
            [
                'name' => 'Juego de 4',
                'code' => 'JG4',
                'description' => 'Equivalente a 4 unidades',
                'category' => 'count',
                'is_base' => false,
                'factor' => 4.00,
                'state' => 1,
            ],
        ];

        // Unidades de Volumen/Fluidos (Categoría: volume)
        $volumeUnits = [
            [
                'name' => 'Litro',
                'code' => 'L',
                'description' => 'Unidad base para volumen de fluidos',
                'category' => 'volume',
                'is_base' => true,
                'factor' => 1.00,
                'state' => 1,
            ],
            [
                'name' => 'Galón Americano',
                'code' => 'GAL_US',
                'description' => 'Galón Americano de 3.78541 Litros',
                'category' => 'volume',
                'is_base' => false,
                'factor' => 3.78541,
                'state' => 1,
            ],
            [
                'name' => 'Galón de 4 Litros',
                'code' => 'GAL_4L',
                'description' => 'Galón de 4 Litros exactos',
                'category' => 'volume',
                'is_base' => false,
                'factor' => 4.00,
                'state' => 1,
            ],
            [
                'name' => 'Mililitro',
                'code' => 'ML',
                'description' => 'Mililitro (0.001 Litros)',
                'category' => 'volume',
                'is_base' => false,
                'factor' => 0.001,
                'state' => 1,
            ],
        ];

        // Insertar unidades de conteo
        foreach ($countUnits as $unit) {
            Unit::updateOrCreate(
                ['code' => $unit['code']],
                $unit
            );
        }

        // Insertar unidades de volumen
        foreach ($volumeUnits as $unit) {
            Unit::updateOrCreate(
                ['code' => $unit['code']],
                $unit
            );
        }

        $this->command->info('✅ Unidades de medida creadas exitosamente');
        $this->command->info('   - Unidades de Conteo: UND, PAR, JG4');
        $this->command->info('   - Unidades de Volumen: L, GAL_US, GAL_4L, ML');
    }
}
