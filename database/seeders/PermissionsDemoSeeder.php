<?php

namespace Database\Seeders;

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

        Permission::create(['guard_name' => 'api', 'name' => 'register_car']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_car']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_car']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_car']);

        // create roles and assign existing permissions

        $role3 = Role::create(['guard_name' => 'api', 'name' => 'Super-Admin']);
        // gets all permissions via Gate::before rule; see AuthServiceProvider

        $user = \App\Models\User::factory()->create([
            'name' => 'Super-Admin User',
            'email' => 'laravest@gmail.com',
            'password' => bcrypt('12345678'),
        ]);
        $user->assignRole($role3);
    }
}
