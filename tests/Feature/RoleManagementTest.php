<?php

use App\Models\User;
use Database\Seeders\SpatiePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('guests are redirected from the role management page', function () {
    $this->get(route('roles.index'))->assertRedirect(route('login'));
});

test('a user without the manage roles permission is forbidden', function () {
    $user = adminUser();

    $this->actingAs($user);

    $this->get(route('roles.index'))->assertForbidden();
});

test('a superadmin can visit the role management page', function () {
    $user = superadminUser();
    $this->actingAs($user);

    $this->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('roles/index')->has('roles'));
});

test('the role management page lists roles with their permissions and user counts', function () {
    $user = superadminUser();

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('roles.data', 3)
            ->where('roles.data.0.name', 'superadmin')
            ->where('roles.data.0.users_count', 1)
            ->where('roles.data.1.name', 'admin')
            ->where('roles.data.1.users_count', 1)
            ->where('roles.data.2.name', 'user')
            ->where('roles.data.2.users_count', 1)
        );
});

test('role permissions are listed view permissions first then manage permissions alphabetically', function () {
    $this->seed(SpatiePermissionSeeder::class);

    $user = superadminUser();
    $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
    $role->syncPermissions(['manage users', 'view reports', 'view dashboard', 'manage roles']);

    $this->actingAs($user);

    $expectedAvailable = Permission::pluck('name')
        ->sortBy(fn (string $name) => [str_starts_with($name, 'view ') ? 0 : 1, $name])
        ->values()
        ->all();

    $this->get(route('roles.index', ['search' => 'editor']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('roles.data', 1)
            ->where('roles.data.0.name', 'editor')
            ->where('roles.data.0.permissions', ['view dashboard', 'view reports', 'manage roles', 'manage users'])
            ->where('availablePermissions', $expectedAvailable)
        );
});

test('roles can be searched by name', function () {
    $user = superadminUser();
    Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

    $this->actingAs($user);

    $this->get(route('roles.index', ['search' => 'edit']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('roles.data', 1)
            ->where('roles.data.0.name', 'editor')
            ->where('filters.search', 'edit')
        );
});

test('a superadmin can create a role with permissions', function () {
    $user = superadminUser();

    Permission::firstOrCreate(['name' => 'manage users', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage roles', 'guard_name' => 'web']);

    $this->actingAs($user);

    $this->post(route('roles.store'), [
        'name' => 'editor',
        'permissions' => ['manage users'],
    ])
        ->assertRedirect(route('roles.index'));

    $this->assertDatabaseHas('spatie_roles', ['name' => 'editor']);
    $role = Role::where('name', 'editor')->first();
    expect($role->hasPermissionTo('manage users'))->toBeTrue();
    expect($role->hasPermissionTo('manage roles'))->toBeFalse();
});

test('a role name must be unique', function () {
    $user = superadminUser();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->actingAs($user);

    $this->post(route('roles.store'), [
        'name' => 'admin',
        'permissions' => ['manage users'],
    ])->assertSessionHasErrors('name');
});

test('a superadmin can update a role', function () {
    $user = superadminUser();
    Permission::firstOrCreate(['name' => 'manage users', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage roles', 'guard_name' => 'web']);
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $role->syncPermissions(['manage users']);

    $this->actingAs($user);

    $this->patch(route('roles.update', $role), [
        'name' => 'manager',
        'permissions' => ['manage roles'],
    ])
        ->assertRedirect(route('roles.index'));

    $this->assertDatabaseHas('spatie_roles', ['id' => $role->id, 'name' => 'manager']);
    $role->refresh();
    expect($role->hasPermissionTo('manage roles'))->toBeTrue();
    expect($role->hasPermissionTo('manage users'))->toBeFalse();
});

test('a superadmin can delete a role', function () {
    $user = superadminUser();
    $role = Role::create(['name' => 'temp-role', 'guard_name' => 'web']);

    $this->actingAs($user);

    $this->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));

    $this->assertDatabaseMissing('spatie_roles', ['id' => $role->id]);
});

test('the superadmin role cannot be updated', function () {
    $user = superadminUser();
    $superadmin = Role::where('name', 'superadmin')->first() ?? Role::create(['name' => 'superadmin', 'guard_name' => 'web']);

    $this->actingAs($user);

    $this->patch(route('roles.update', $superadmin), [
        'name' => 'owner',
        'permissions' => ['manage users'],
    ])->assertForbidden();

    $this->assertDatabaseHas('spatie_roles', ['id' => $superadmin->id, 'name' => 'superadmin']);
});

test('the superadmin role cannot be deleted', function () {
    $user = superadminUser();
    $superadmin = Role::where('name', 'superadmin')->first() ?? Role::create(['name' => 'superadmin', 'guard_name' => 'web']);

    $this->actingAs($user);

    $this->delete(route('roles.destroy', $superadmin))->assertForbidden();

    $this->assertDatabaseHas('spatie_roles', ['id' => $superadmin->id]);
});

test('the spatie seeder creates the default roles', function () {
    $this->seed(SpatiePermissionSeeder::class);

    $this->assertDatabaseHas('spatie_roles', ['name' => 'superadmin']);
    $this->assertDatabaseHas('spatie_roles', ['name' => 'admin']);
    $this->assertDatabaseHas('spatie_roles', ['name' => 'user']);

    $superadmin = Role::where('name', 'superadmin')->first();
    expect($superadmin->hasPermissionTo('manage users'))->toBeTrue();
    expect($superadmin->hasPermissionTo('manage roles'))->toBeTrue();
    expect($superadmin->hasPermissionTo('manage activity log'))->toBeTrue();

    $admin = Role::where('name', 'admin')->first();
    expect($admin->hasPermissionTo('manage users'))->toBeTrue();
    expect($admin->hasPermissionTo('manage roles'))->toBeFalse();
});

test('a newly registered user automatically receives the user role', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage users', 'guard_name' => 'web']);

    $this->post('/register', [
        'name' => 'New Member',
        'email' => 'member@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'member@example.com',
        'role' => 'user',
    ]);

    $user = User::where('email', 'member@example.com')->first();
    expect($user->hasRole('user'))->toBeTrue();
});
