<?php

use App\Models\User;

test('guests are redirected from the user management page', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

test('a user without the manage users permission is forbidden', function () {
    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user);

    $this->get(route('users.index'))->assertForbidden();
});

test('authenticated users can visit the user management page', function () {
    $admin = adminUser();
    $this->actingAs($admin);

    $this->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('users/index')->has('users'));
});

test('the user management page lists users with their details', function () {
    $admin = adminUser();
    $user = User::factory()->unverified()->create(['name' => 'Z User', 'role' => 'user']);

    $this->actingAs($admin);

    $this->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 2)
            ->where('users.data.0.name', $admin->name)
            ->where('users.data.0.email', $admin->email)
            ->where('users.data.0.role', 'admin')
            ->where('users.data.1.email', $user->email)
            ->where('users.data.1.role', 'user')
            ->where('users.data.1.email_verified_at', null)
        );
});

test('users are ordered by role priority then creation date', function () {
    $admin = adminUser();
    $superadmin = User::factory()->create(['name' => 'A Super', 'role' => 'superadmin']);
    $user = User::factory()->create(['name' => 'C User', 'role' => 'user']);

    $this->actingAs($admin);

    $this->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 3)
            ->where('users.data.0.name', $superadmin->name)
            ->where('users.data.0.role', 'superadmin')
            ->where('users.data.1.name', $admin->name)
            ->where('users.data.1.role', 'admin')
            ->where('users.data.2.name', $user->name)
            ->where('users.data.2.role', 'user')
        );
});

test('users can be searched by name or email', function () {
    $admin = adminUser(['email' => 'admin@local.test']);
    $target = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $other = User::factory()->create(['name' => 'John Smith', 'email' => 'john@example.com']);

    $this->actingAs($admin);

    $this->get(route('users.index', ['search' => 'jane']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $target->id)
            ->where('filters.search', 'jane')
        );

    $this->get(route('users.index', ['search' => 'example.com']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('users.data', 2));
});

test('users are paginated', function () {
    $admin = adminUser();
    User::factory()->count(15)->create(['role' => 'user']);

    $this->actingAs($admin);

    $this->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 10)
            ->where('users.total', 16)
            ->where('users.last_page', 2)
            ->where('users.current_page', 1)
        );
});

test('users can be paginated with a custom per page value', function () {
    $admin = adminUser();
    User::factory()->count(20)->create(['role' => 'user']);

    $this->actingAs($admin);

    $this->get(route('users.index', ['per_page' => 5]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 5)
            ->where('users.total', 21)
            ->where('users.per_page', 5)
            ->where('users.last_page', 5)
            ->where('filters.per_page', 5)
        );
});

test('an admin can update a user', function () {
    $admin = adminUser();
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($admin);

    $this->patch(route('users.update', $user), [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ])
        ->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'role' => 'user',
    ]);
});

test('updating a user email resets the verification status', function () {
    $admin = adminUser();
    $user = User::factory()->create(['email' => 'old@example.com']);

    $this->actingAs($admin);

    $this->patch(route('users.update', $user), [
        'name' => $user->name,
        'email' => 'new@example.com',
    ])->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'new@example.com',
        'email_verified_at' => null,
    ]);
});

test('an admin can change a user role', function () {
    $admin = adminUser();
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($admin);

    $this->patch(route('users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'role' => 'admin',
    ])->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => 'admin',
    ]);
});

test('a user without the manage users permission cannot change a role', function () {
    $user = User::factory()->create(['role' => 'user']);
    $target = User::factory()->create(['role' => 'user']);

    $this->actingAs($user);

    $this->patch(route('users.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => 'admin',
    ])->assertForbidden();
});

test('a superadmin can change a user role', function () {
    $superadmin = superadminUser();
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($superadmin);

    $this->patch(route('users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'role' => 'admin',
    ])->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => 'admin',
    ]);
});

test('an admin can delete a user', function () {
    $admin = adminUser();
    $user = User::factory()->create();

    $this->actingAs($admin);

    $this->delete(route('users.destroy', $user))->assertRedirect(route('users.index'));

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('a superadmin user cannot be updated', function () {
    $admin = adminUser();
    $superadmin = User::factory()->create(['role' => 'superadmin']);

    $this->actingAs($admin);

    $this->patch(route('users.update', $superadmin), [
        'name' => 'Hacked',
        'email' => $superadmin->email,
    ])->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $superadmin->id, 'name' => $superadmin->name]);
});

test('a superadmin user cannot be deleted', function () {
    $admin = adminUser();
    $superadmin = User::factory()->create(['role' => 'superadmin']);

    $this->actingAs($admin);

    $this->delete(route('users.destroy', $superadmin))->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $superadmin->id]);
});
