<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---- plans ----
        $plans = [
            ['name' => 'Basic',      'description' => 'For individuals to track personal devices.', 'monthly_price' => 300,  'annual_price' => 3000,  'max_products' => 50,  'storage_limit_mb' => 500,   'max_users' => 1],
            ['name' => 'Family',     'description' => 'For family use (home devices).',            'monthly_price' => 600,  'annual_price' => 6000,  'max_products' => 200, 'storage_limit_mb' => 2048,  'max_users' => 5],
            ['name' => 'Business',   'description' => 'For small and medium business.',            'monthly_price' => 1200, 'annual_price' => 12000, 'max_products' => 500, 'storage_limit_mb' => 5120,  'max_users' => 10],
            ['name' => 'Enterprise', 'description' => 'For large organizations.',                  'monthly_price' => 2500, 'annual_price' => 25000, 'max_products' => 0,   'storage_limit_mb' => 20480, 'max_users' => 50],
        ];
        foreach ($plans as $p) {
            Plan::updateOrCreate(['name' => $p['name']], $p);
        }

        // ---- permissions (these slugs are enforced in routes/middleware) ----
        $perms = [
            ['products.view',    'View products & warranties', 'Products'],
            ['products.create',  'Add products',               'Products'],
            ['products.edit',    'Edit products & warranties', 'Products'],
            ['products.delete',  'Delete products',            'Products'],
            ['documents.manage', 'Upload / delete documents',  'Documents'],
        ];
        foreach ($perms as [$slug, $label, $group]) {
            Permission::updateOrCreate(['slug' => $slug], ['label' => $label, 'group' => $group, 'is_system' => true, 'is_active' => true]);
        }

        // ---- starter roles (super admin can edit / add more) ----
        $roles = [
            'Viewer'  => ['Can only view products and warranties.',            ['products.view']],
            'Editor'  => ['Can add and edit products and upload documents.',   ['products.view', 'products.create', 'products.edit', 'documents.manage']],
            'Manager' => ['Full product, document and file access.',           ['products.view', 'products.create', 'products.edit', 'products.delete', 'documents.manage']],
        ];
        foreach ($roles as $name => [$desc, $slugs]) {
            $role = Role::updateOrCreate(['name' => $name], ['description' => $desc, 'is_active' => true]);
            $role->permissions()->sync(Permission::whereIn('slug', $slugs)->pluck('id'));
        }

        // ---- sample categories + subcategories (super admin can change everything) ----
        $tree = [
            'Electronics'      => ['Mobile Phone', 'Laptop', 'Desktop / Computer', 'Monitor', 'Television', 'Printer', 'Camera'],
            'Home Appliances'  => ['Refrigerator', 'Air Conditioner', 'Washing Machine', 'Microwave Oven', 'Water Purifier'],
            'Vehicles'         => ['Car', 'Motorcycle', 'Bicycle'],
            'Other'            => ['Other'],
        ];
        $i = 1;
        foreach ($tree as $catName => $subs) {
            $cat = Category::updateOrCreate(['name' => $catName], ['sort_order' => $i++, 'is_active' => true]);
            foreach ($subs as $j => $subName) {
                Subcategory::updateOrCreate(['category_id' => $cat->id, 'name' => $subName], ['sort_order' => $j + 1, 'is_active' => true]);
            }
        }

        // ---- super admin (change this password after first login!) ----
        User::updateOrCreate(['email' => 'admin@gmail.com'], [
            'name' => 'Super Admin',
            'password' => 'Admin12345',
            'role' => 'super_admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }
}
