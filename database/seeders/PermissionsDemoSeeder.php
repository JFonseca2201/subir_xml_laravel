<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsDemoSeeder extends Seeder
{
    /**
     * Create the initial roles and permissions.
     *
     * @return void
     */
    public function run()
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['guard_name' => 'api', 'name' => 'dashboard']);
        // create permissions
        Permission::create(['guard_name' => 'api', 'name' => 'register_role']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_role']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_role']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_role']);

        Permission::create(['guard_name' => 'api', 'name' => 'register_user']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_user']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_user']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_user']);

        Permission::create(['guard_name' => 'api', 'name' => 'settings']);

        Permission::create(['guard_name' => 'api', 'name' => 'register_product']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_product']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_product']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_product']);
        Permission::create(['guard_name' => 'api', 'name' => 'show_inventory_product']);
        Permission::create(['guard_name' => 'api', 'name' => 'show_wallet_price_product']);

        Permission::create(['guard_name' => 'api', 'name' => 'register_client']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_client']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_client']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_client']);

        Permission::create(['guard_name' => 'api', 'name' => 'register_car']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_car']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_car']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_car']);

        Permission::create(['guard_name' => 'api', 'name' => 'register_employee']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_employee']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_employee']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_employee']);

        Permission::create(['guard_name' => 'api', 'name' => 'register_partner']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_partner']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_partner']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_partner']);

        Permission::create(['guard_name' => 'api', 'name' => 'register_sale']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_sale']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_sale']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_sale']);

        Permission::create(['guard_name' => 'api', 'name' => 'return']);

        Permission::create(['guard_name' => 'api', 'name' => 'register_purchase']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_purchase']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_purchase']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_purchase']);

        Permission::create(['guard_name' => 'api', 'name' => 'register_transport']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_transport']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_transport']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_transport']);

        Permission::create(['guard_name' => 'api', 'name' => 'conversions']);
        Permission::create(['guard_name' => 'api', 'name' => 'kardex']);

        // Invoice permissions
        Permission::create(['guard_name' => 'api', 'name' => 'register_invoice']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_invoice']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_invoice']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_invoice']);

        // Transaction permissions
        Permission::create(['guard_name' => 'api', 'name' => 'register_transaction']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_transaction']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_transaction']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_transaction']);

        // Transfer permissions
        Permission::create(['guard_name' => 'api', 'name' => 'register_transfer']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_transfer']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_transfer']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_transfer']);

        // Supplier permissions
        Permission::create(['guard_name' => 'api', 'name' => 'register_supplier']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_supplier']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_supplier']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_supplier']);

        // Employee payment permissions
        Permission::create(['guard_name' => 'api', 'name' => 'register_employee_payment']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_employee_payment']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_employee_payment']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_employee_payment']);

        // Partner contribution permissions
        Permission::create(['guard_name' => 'api', 'name' => 'register_partner_contribution']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_partner_contribution']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_partner_contribution']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_partner_contribution']);

        // Specific permissions
        Permission::create(['guard_name' => 'api', 'name' => 'import_xml']);
        Permission::create(['guard_name' => 'api', 'name' => 'export_data']);
        Permission::create(['guard_name' => 'api', 'name' => 'view_reports']);
        Permission::create(['guard_name' => 'api', 'name' => 'manage_settings']);
        Permission::create(['guard_name' => 'api', 'name' => 'approve_transactions']);



        // create roles and assign existing permissions

        $roleSuperAdmin = Role::create(['guard_name' => 'api', 'name' => 'Super-Admin']);
        // gets all permissions via Gate::before rule; see AuthServiceProvider

        // Create Admin role with most permissions except user/role management
        $roleAdmin = Role::create(['guard_name' => 'api', 'name' => 'Admin']);
        $roleAdmin->givePermissionTo([
            'dashboard',
            'settings',
            'register_product',
            'list_product',
            'edit_product',
            'register_client',
            'list_client',
            'edit_client',
            'register_car',
            'list_car',
            'edit_car',
            'register_employee',
            'list_employee',
            'edit_employee',
            'register_partner',
            'list_partner',
            'edit_partner',
            'register_sale',
            'list_sale',
            'edit_sale',
            'return',
            'register_purchase',
            'list_purchase',
            'edit_purchase',
            'register_transport',
            'list_transport',
            'edit_transport',
            'conversions',
            'kardex',
            'register_invoice',
            'list_invoice',
            'edit_invoice',
            'register_transaction',
            'list_transaction',
            'edit_transaction',
            'register_transfer',
            'list_transfer',
            'edit_transfer',
            'register_supplier',
            'list_supplier',
            'edit_supplier',
            'register_employee_payment',
            'list_employee_payment',
            'edit_employee_payment',
            'register_partner_contribution',
            'list_partner_contribution',
            'edit_partner_contribution',
            'import_xml',
            'export_data',
            'view_reports',
            'approve_transactions'
        ]);

        // Create Manager role with operational permissions
        $roleManager = Role::create(['guard_name' => 'api', 'name' => 'Manager']);
        $roleManager->givePermissionTo([
            'dashboard',
            'list_product',
            'edit_product',
            'list_client',
            'edit_client',
            'list_car',
            'edit_car',
            'list_employee',
            'edit_employee',
            'list_partner',
            'edit_partner',
            'register_sale',
            'list_sale',
            'edit_sale',
            'return',
            'list_purchase',
            'edit_purchase',
            'list_transport',
            'edit_transport',
            'conversions',
            'kardex',
            'list_invoice',
            'edit_invoice',
            'list_transaction',
            'edit_transaction',
            'list_transfer',
            'edit_transfer',
            'list_supplier',
            'edit_supplier',
            'list_employee_payment',
            'edit_employee_payment',
            'list_partner_contribution',
            'edit_partner_contribution',
            'import_xml',
            'export_data',
            'view_reports'
        ]);

        // Create Employee role with basic permissions
        $roleEmployee = Role::create(['guard_name' => 'api', 'name' => 'Employee']);
        $roleEmployee->givePermissionTo([
            'dashboard',
            'list_product',
            'list_client',
            'list_car',
            'register_sale',
            'list_sale',
            'list_purchase',
            'list_transport',
            'conversions',
            'kardex',
            'list_invoice',
            'list_transaction',
            'list_transfer',
            'list_supplier',
            'view_reports'
        ]);

        $user = \App\Models\User::factory()->create([
            'name' => 'Super-Admin User',
            'email' => 'laravest@gmail.com',
            'password' => bcrypt('12345678'),
        ]);
        $user->assignRole($roleSuperAdmin);

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
