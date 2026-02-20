<?php

namespace Database\Seeders;

use App\Models\Config\Sucursale;
use Illuminate\Database\Seeder;

class SucursaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sucursales = [
            [
                'name' => 'Matriz Principal',
                'address' => 'Av. Principal 123',
                'ruc' => '1793192550001',
                'trade_name' => 'Empresa Principal S.A.',
                'secuencial_factura' => '000000001',
                'serie_factura' => '001-001',
                'establecimiento' => '001',
                'punto_emision' => '001',
                'ambiente' => 1, // Pruebas
                'tipo_emision' => 1, // Normal
                'obligado_contabilidad' => 'SI',
                'status' => 'active',
            ],
        ];

        foreach ($sucursales as $sucursal) {
            Sucursale::updateOrCreate(
                ['ruc' => $sucursal['ruc'], 'establecimiento' => $sucursal['establecimiento']],
                $sucursal,
            );
        }
    }
}
