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

        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'dashboard']);
        // create permissions
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'register_role']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'list_role']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'edit_role']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_role']);

        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'register_user']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'list_user']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'edit_user']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_user']);

        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'settings']);

        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'register_product']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'list_product']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'edit_product']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_product']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'show_inventory_product']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'show_wallet_price_product']);

        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'register_client']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'list_client']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'edit_client']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_client']);

        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'register_car']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'list_car']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'edit_car']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_car']);

        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'register_employee']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'list_employee']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'edit_employee']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_employee']);

        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'register_partner']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'list_partner']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'edit_partner']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_partner']);

        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'register_sale']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'list_sale']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'edit_sale']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_sale']);

        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'return']);

        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'register_purchase']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'list_purchase']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'edit_purchase']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_purchase']);

        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'register_transport']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'list_transport']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'edit_transport']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_transport']);

        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'conversions']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'kardex']);

        // Invoice permissions
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'register_invoice']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'list_invoice']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'edit_invoice']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_invoice']);

        // Transaction permissions
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'register_transaction']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'list_transaction']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'edit_transaction']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_transaction']);

        // Transfer permissions
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'register_transfer']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'list_transfer']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'edit_transfer']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_transfer']);

        // Supplier permissions
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'register_supplier']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'list_supplier']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'edit_supplier']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_supplier']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_supplier']);

        // Employee payment permissions
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'register_employee_payment']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'list_employee_payment']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'edit_employee_payment']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_employee_payment']);

        // Employee permissions
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'register_employee']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'list_employee']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'edit_employee']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_employee']);

        // Partner contribution permissions
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'register_partner_contribution']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'list_partner_contribution']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'edit_partner_contribution']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'delete_partner_contribution']);

        // Specific permissions
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'import_xml']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'export_data']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'view_reports']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'manage_settings']);
        Permission::updateOrCreate(['guard_name' => 'api', 'name' => 'approve_transactions']);



        // create roles and assign existing permissions

        $roleSuperAdmin = Role::updateOrCreate(['guard_name' => 'api', 'name' => 'Super-Admin']);
        // gets all permissions via Gate::before rule; see AuthServiceProvider

        // Create Admin role with most permissions except user/role management
        $roleAdmin = Role::updateOrCreate(['guard_name' => 'api', 'name' => 'Admin']);
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
            'delete_employee',
            'register_employee_payment',
            'list_employee_payment',
            'edit_employee_payment',
            'delete_employee_payment',
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
            'approve_transactions',
        ]);

        // Create Manager role with operational permissions
        $roleManager = Role::updateOrCreate(['guard_name' => 'api', 'name' => 'Manager']);
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
            'view_reports',
        ]);

        // Create Employee role with basic permissions
        $roleEmployee = Role::updateOrCreate(['guard_name' => 'api', 'name' => 'Employee']);
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
            'view_reports',
        ]);

        $user = \App\Models\User::updateOrCreate(
            ['identification' => '1793192550001'],
            [
                'name' => 'Super-Admin',
                'surname' => 'User',
                'email' => 'laravest@gmail.com',
                'type_document' => 'CI',
                'password' => bcrypt('12345678'),
            ]
        );
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
            Account::updateOrCreate(['code' => $account['code']], $account);
        }

        // Create sucursale data
        $sucursale = [
            'name' => 'COMPAÑIA DE SERVICIOS Y MANTENIMIENTO AUTOMOTRIZ LUXURY EVYS CIA. LTDA.',
            'address' => 'SUR DE QUITO, SECTOR EL BEATERIO S49B Y E1C',
            'ruc' => '1793192550001',
            'phone' => '022698134',
            'email' => 'comp.luxuryevys@gmail.com',
            'trade_name' => 'LUXURY EVYS CIA. LTDA.',
            'secuencial_factura' => '000000001',
            'serie_factura' => '001-001',
            'establecimiento' => '001',
            'punto_emision' => '001',
            'ambiente' => 1, // 1: Pruebas, 2: Produccion
            'tipo_emision' => 1, // 1: Normal
            'firma_electronica' => null,
            'password_firma' => null,
            'logo' => null,
            'obligado_contabilidad' => 'SI',
            'contribuyente_especial' => null,
            'status' => 'active',
        ];

        \App\Models\Config\Sucursale::updateOrCreate(
            ['ruc' => $sucursale['ruc'], 'name' => $sucursale['name']],
            $sucursale,
        );

        // ============= CREAR UNIDADES ==============
        $units = [
            ['name' => 'UNIDAD', 'state' => 1],
            ['name' => 'CAJA', 'state' => 1],
            ['name' => 'KILOGRAMO', 'state' => 1],
            ['name' => 'LITRO', 'state' => 1],
            ['name' => 'METRO', 'state' => 1],
            ['name' => 'PAR', 'state' => 1],
            ['name' => 'DOCENA', 'state' => 1],
            ['name' => 'GALÓN', 'state' => 1],
            ['name' => 'BULTO', 'state' => 1],
        ];

        foreach ($units as $unit) {
            \App\Models\Config\Unit::updateOrCreate(
                ['name' => $unit['name']],
                $unit
            );
        }

        // ============= CREAR CATEGORÍAS ==============
        $categories = [
            ['title' => 'REPUESTOS AUTOMOTRICES', 'imagen' => 'http://localhost:8000/storage/categories/repuestos_automotrices.png', 'state' => 1],
            ['title' => 'LUBRICANTES Y ACEITES', 'imagen' => 'http://localhost:8000/storage/categories/lubricantes_aceites.png', 'state' => 1],
            ['title' => 'HERRAMIENTAS', 'imagen' => 'http://localhost:8000/storage/categories/herramientas.png', 'state' => 1],
            ['title' => 'EQUIPOS DE TALLER', 'imagen' => 'http://localhost:8000/storage/categories/equipos_taller.png', 'state' => 1],
            ['title' => 'LLANTAS Y NEUMÁTICOS', 'imagen' => 'http://localhost:8000/storage/categories/llantas_neumaticos.png', 'state' => 1],
            ['title' => 'BATERÍAS', 'imagen' => 'http://localhost:8000/storage/categories/baterias.png', 'state' => 1],
            ['title' => 'FILTROS', 'imagen' => 'http://localhost:8000/storage/categories/filtros.png', 'state' => 1],
            ['title' => 'FRENOS', 'imagen' => 'http://localhost:8000/storage/categories/frenos.png', 'state' => 1],
            ['title' => 'SUSPENSIÓN Y DIRECCIÓN', 'imagen' => 'http://localhost:8000/storage/categories/suspension_direccion.png', 'state' => 1],
            ['title' => 'SISTEMA ELÉCTRICO', 'imagen' => 'http://localhost:8000/storage/categories/sistema_electrico.png', 'state' => 1],
            ['title' => 'CARROCERÍA Y ACCESORIOS', 'imagen' => 'http://localhost:8000/storage/categories/carroceria_accesorios.png', 'state' => 1],
            ['title' => 'SERVICIOS DE TALLER', 'imagen' => 'http://localhost:8000/storage/categories/servicios_taller.png', 'state' => 1],
            ['title' => 'PRODUCTOS DE LIMPIEZA', 'imagen' => 'http://localhost:8000/storage/categories/productos_limpieza.png', 'state' => 1],
            ['title' => 'ADITIVOS Y QUÍMICOS', 'imagen' => 'http://localhost:8000/storage/categories/aditivos_quimicos.png', 'state' => 1],
        ];

        foreach ($categories as $category) {
            \App\Models\Config\ProductCategorie::updateOrCreate(
                ['title' => $category['title'], 'imagen' => $category['imagen']],
                $category
            );
        }

        // ============= CREAR ALMACENES ==============
        $warehouses = [
            ['name' => 'LUXURY EVYS CIA. LTDA.', 'sucursale_id' => '1', 'state' => 0],
            ['name' => 'ALMACÉN SECUNDARIO', 'sucursale_id' => '1', 'state' => 0],
            ['name' => 'ALMACÉN DE REPUESTOS', 'sucursale_id' => '1', 'state' => 0],
            ['name' => 'ALMACÉN DE LUBRICANTES', 'sucursale_id' => '1', 'state' => 0],
            ['name' => 'ALMACÉN DE HERRAMIENTAS', 'sucursale_id' => '1', 'state' => 0],
        ];

        foreach ($warehouses as $warehouse) {
            \App\Models\Config\Warehouse::updateOrCreate(
                ['name' => $warehouse['name'], 'sucursale_id' => $warehouse['sucursale_id']],
                $warehouse
            );
        }
    }
}
