<?php

namespace Database\Seeders;
use Illuminate\Support\Facades\Hash;

use App\Models\User;
use App\Models\Store;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Contact;
use App\Models\Product;
use App\Models\Sale;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Starting database seeding...');

        // Create stores
        $this->command->info('Creating stores...');
        Store::create([
            'name' => 'INFO SHOP',
            'address'=>'Main Street, Oddamavadi',
            'contact_number'=>'00000001',
            'sale_prefix'=>'IS',
            'current_sale_number'=>0,
            'tax_rate' => 0.08,
            'currency_code' => 'USD',
            'email' => 'contact@infoshop.com',
        ]);

        Store::factory(3)->create();

        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        $permissions = [
            'pos',
            'products',
            'inventory',
            'sales',
            'customers',
            'vendors',
            'collections',
            'expenses',
            'quotations',
            'reloads',
            'cheques',
            'sold-items',
            'purchases',
            'payments',
            'stores',
            'employees',
            'payroll',
            'media',
            'settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
        $superAdminRole->givePermissionTo(Permission::all());

        $adminPermissions = [
            'pos',
            'products',
            'inventory',
            'sales',
            'customers',
            'vendors',
            'collections',
            'expenses',
            'quotations',
            'reloads',
            'cheques',
            'sold-items',
            'purchases',
            'payments',
            'stores',
            'employees',
            'payroll',
            'media',
            'settings',
        ];
        $adminRole->givePermissionTo($adminPermissions);

        $userPermissions = [
            'products',
            'pos'
        ];
        $userRole->givePermissionTo($userPermissions);
        
        $superAdmin=User::create([
            'name' => 'Admin',
            'user_name'=>'master',
            'user_role'=>'super-admin',
            'email' => 'master@infomax.lk',
            'store_id' => 1,
            'password' => Hash::make('8236'),
        ]);
        $superAdmin->assignRole($superAdminRole);

        $admin=User::create([
            'name' => 'Admin',
            'user_name'=>'admin',
            'user_role'=>'admin',
            'email' => 'admin@infomax.lk',
            'store_id' => 1,
            'password' => Hash::make('8236'),
        ]);
        $admin->assignRole($adminRole);

        // Create additional users
        $this->command->info('Creating users...');
        User::factory(10)->create();

        // Create categories and brands
        $this->command->info('Creating categories...');
        Category::factory(8)->create();
        // Create some sub-categories
        Category::factory(12)->asChild()->create();

        $this->command->info('Creating brands...');
        Brand::factory(15)->create();

        // Create contacts (customers and vendors)
        $this->command->info('Creating contacts...');
        Contact::factory(30)->customers()->create();
        Contact::factory(10)->vendors()->create();

        // Create products
        $this->command->info('Creating products...');
        Product::factory(100)->create();

        // Create sales
        $this->command->info('Creating sales...');
        Sale::factory(200)->create();

        $this->command->info('Creating additional sample data...');
        $this->call([
            ContactSeeder::class,
            SettingSeeder::class,
        ]);

        $this->command->info('Database seeding completed successfully!');
    }
}
