<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function superadminUser(array $attributes = []): User
{
    foreach (['manage users', 'manage roles', 'manage activity log'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $spatieRole = SpatieRole::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    $spatieRole->syncPermissions(['manage users', 'manage roles', 'manage activity log']);

    $user = User::factory()->create(['role' => 'superadmin', ...$attributes]);
    $user->syncRoles(['superadmin']);

    return $user;
}

function adminUser(array $attributes = []): User
{
    foreach (['manage users', 'manage roles', 'manage activity log'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $spatieRole = SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $spatieRole->syncPermissions(['manage users']);

    $user = User::factory()->create(['role' => 'admin', ...$attributes]);
    $user->syncRoles(['admin']);

    return $user;
}
