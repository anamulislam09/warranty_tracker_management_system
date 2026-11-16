<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // permissions the customer turned OFF for one user, even though the user's role has them
        Schema::table('users', function (Blueprint $t) {
            $t->json('denied_permissions')->nullable()->after('permissions');
        });

        $now = now();

        // two new permissions: so that the Dashboard and Notifications pages can also be given / taken away
        foreach ([['dashboard.view', 'View dashboard', 'Dashboard'], ['notifications.view', 'View notifications', 'Notifications']] as [$slug, $label, $group]) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], [
                'label' => $label, 'group' => $group, 'is_active' => true, 'is_system' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $permId = fn (string $slug) => DB::table('permissions')->where('slug', $slug)->value('id');

        // roles that already exist keep working exactly as before: they get the two new permissions
        foreach (DB::table('roles')->pluck('id') as $roleId) {
            foreach (['dashboard.view', 'notifications.view'] as $slug) {
                DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permId($slug)]);
            }
        }

        // the three roles of the business logic (super admin can edit them or add more)
        $roles = [
            'Normal User' => ['Can see the dashboard, products and notifications.',
                ['dashboard.view', 'products.view', 'notifications.view']],
            'Staff'       => ['Can add and edit products and upload files.',
                ['dashboard.view', 'products.view', 'products.create', 'products.edit', 'documents.manage', 'notifications.view']],
            'Manager'     => ['Full access to products and files.',
                ['dashboard.view', 'products.view', 'products.create', 'products.edit', 'products.delete', 'documents.manage', 'notifications.view']],
        ];
        foreach ($roles as $name => [$description, $slugs]) {
            DB::table('roles')->updateOrInsert(['name' => $name], [
                'description' => $description, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $roleId = DB::table('roles')->where('name', $name)->value('id');
            foreach ($slugs as $slug) {
                if ($id = $permId($slug)) {
                    DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $id]);
                }
            }
        }

        // users that were created before keep their dashboard and notifications
        DB::table('users')->where('role', 'member')->get(['id', 'permissions'])->each(function ($user) {
            $list = json_decode($user->permissions ?? '[]', true) ?: [];
            $list = array_values(array_unique(array_merge($list, ['dashboard.view', 'notifications.view'])));
            DB::table('users')->where('id', $user->id)->update(['permissions' => json_encode($list)]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('denied_permissions');
        });
    }
};
