<?php

namespace App\Http\Controllers;

use App\Events\UsersChanged;
use App\Http\Requests\UserUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Show the user management page.
     */
    public function index(Request $request): Response
    {
        $perPage = $request->integer('per_page', 10);

        $users = User::query()
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByRaw("CASE role WHEN 'superadmin' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Update the given user's information.
     */
    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        abort_if($user->hasRole('superadmin'), 403);

        if ($request->has('role') && ! $request->user()->canManageUsers()) {
            abort(403);
        }

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($request->has('role')) {
            Role::firstOrCreate([
                'name' => (string) $request->string('role'),
                'guard_name' => 'web',
            ]);

            $user->syncRoles([(string) $request->string('role')]);
        }

        UsersChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.index');
    }

    /**
     * Delete the given user.
     */
    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->hasRole('superadmin'), 403);

        $user->delete();

        UsersChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('users.index');
    }
}
