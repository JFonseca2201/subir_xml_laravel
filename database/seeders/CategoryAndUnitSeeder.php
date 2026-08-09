<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategoryAndUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Materia Prima',
            'Insumos y Desechables',
            'Operación / Mano de Obra'
        ];

        foreach ($categories as $category) {
            \App\Models\Category::firstOrCreate(['name' => $category]);
        }

        $units = [
            'LIBRAS',
            'UNIDADES',
            'MANOS',
            'KILOGRAMOS',
            'LITROS',
            'GLOBAL / SERVICIO'
        ];

        foreach ($units as $unit) {
            \App\Models\UnitType::firstOrCreate(['name' => $unit]);
        }
    }
}
