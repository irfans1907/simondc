<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SpatiePermissionSeeder extends Seeder
{
    /**
     * Seed spatie permissions and roles.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view dashboard',
            'view monitoring',
            'view reports',
            'view asset management',
            'manage files',
            'manage menus',
            'manage users',
            'manage roles',
            'manage permissions',
            'manage activity log',
            'manage asset management',
            'view rack management',
            'manage rack management',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Seed standard roles with permissions (additive: never remove
        // permissions assigned manually through the UI).
        $superadmin = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadmin->givePermissionTo($permissions);

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo(['manage users']);

        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        // Seed default menus.
        $defaultMenus = [
            ['name' => 'Dashboard', 'group' => 'Main Menu', 'url' => '/dashboard', 'icon' => 'LayoutGrid', 'order' => 0],
            ['name' => 'Monitoring', 'group' => 'Main Menu', 'url' => '/monitoring', 'icon' => 'Monitor', 'order' => 1],
            ['name' => 'Reports', 'group' => 'Main Menu', 'url' => '#', 'icon' => 'BookText', 'order' => 2],
            ['name' => 'Asset Management', 'group' => 'Main Menu', 'url' => '/assets', 'icon' => 'FileBox', 'order' => 3],
            ['name' => 'Rack Management', 'group' => 'Main Menu', 'url' => '/rack', 'icon' => 'Server', 'order' => 4],
            ['name' => 'My Files', 'group' => 'File Manager', 'url' => '/files', 'icon' => 'Folders', 'order' => 0],
            ['name' => 'Shared with Me', 'group' => 'File Manager', 'url' => '#', 'icon' => 'FileUser', 'order' => 1],
        ];

        foreach ($defaultMenus as $menu) {
            Menu::firstOrCreate(
                ['name' => $menu['name']],
                ['group' => $menu['group'], 'url' => $menu['url'], 'icon' => $menu['icon'], 'order' => $menu['order'], 'is_active' => true],
            );
        }

        // Seed child menus for the sidebar dropdowns. Keys include parent_id so
        // the three "Overview" children never clobber each other; updateOrCreate
        // keeps already-seeded databases in sync when child urls/orders change
        // between releases.
        $childMenusByParent = [
            'Monitoring' => [
                ['name' => 'Overview', 'url' => '/monitoring/overview', 'order' => 0],
                ['name' => 'Sensors', 'url' => '/monitoring/sensors', 'order' => 1],
                ['name' => 'CCTV', 'url' => '/monitoring/cctv', 'order' => 2],
                ['name' => 'Alarms', 'url' => '/monitoring/alarms', 'order' => 3],
            ],
            'Asset Management' => [
                ['name' => 'Overview', 'url' => '/assets/overview', 'order' => 0],
                ['name' => 'Category List', 'url' => '/assets/categorylist', 'order' => 1],
                ['name' => 'Panel Power List', 'url' => '/assets/panelpowerlist', 'order' => 2],
            ],
            'Rack Management' => [
                ['name' => 'Overview', 'url' => '/rack/overview', 'order' => 0],
                ['name' => 'Rack List', 'url' => '/rack/racklist', 'order' => 1],
            ],
        ];

        foreach ($childMenusByParent as $parentName => $childMenus) {
            $parent = Menu::where('name', $parentName)->first();

            if (! $parent) {
                continue;
            }

            foreach ($childMenus as $child) {
                Menu::updateOrCreate(
                    ['name' => $child['name'], 'parent_id' => $parent->id],
                    [
                        'group' => 'Main Menu',
                        'url' => $child['url'],
                        'icon' => '',
                        'order' => $child['order'],
                        'is_active' => true,
                    ],
                );
            }
        }

        // Assign spatie roles to existing users based on users.role column.
        User::query()->each(function (User $user) {
            if ($user->role) {
                $user->syncRoles([$user->role]);
            }
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
