<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'code' => 'CAJA_CHICA',
                'name' => 'Caja Chica',
                'type' => 'cash',
                'bank_name' => null,
                'initial_balance' => 0,
                'is_active' => true,
                'is_system' => true,
            ],
            [
                'code' => 'BPICH',
                'name' => 'Banco Pichincha',
                'type' => 'bank',
                'bank_name' => 'Banco Pichincha',
                'initial_balance' => 0,
                'is_active' => true,
                'is_system' => true,
            ],
            [
                'code' => 'BGA',
                'name' => 'Banco Guayaquil',
                'type' => 'bank',
                'bank_name' => 'Banco Guayaquil',
                'initial_balance' => 0,
                'is_active' => true,
                'is_system' => true,
            ],
        ];

        foreach ($accounts as $account) {
            Account::updateOrCreate(
                ['code' => $account['code']],
                $account
            );
        }
    }
}
