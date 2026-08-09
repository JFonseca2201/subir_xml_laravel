<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IncomeProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            ['name' => 'Fritada Económica', 'default_price' => 2.50],
            ['name' => 'Fritada Regular', 'default_price' => 3.00],
            ['name' => 'Fritada Familiar', 'default_price' => 5.00],
            ['name' => 'Pescado Frito', 'default_price' => 3.50],
            ['name' => 'Gaseosas', 'default_price' => 0.50],
        ];

        foreach ($products as $product) {
            \App\Models\IncomeProduct::firstOrCreate(
                ['name' => $product['name']],
                ['default_price' => $product['default_price']]
            );
        }
    }
}
