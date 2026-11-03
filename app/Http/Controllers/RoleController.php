<?php

namespace App\Http\Controllers;

use App\Events\PermissionsChanged;
use App\Events\RolesChanged;
use App\Http\Requests\RoleRequest;
use App\Models\SpatieRole as Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    /**
     * Show the role management page.
     */
    public function index(Request $request): Response
    {
        $perPage = $request->integer('per_page', 10);

        $roles = Role::query()
            ->withCount('users')
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderByRaw("CASE name WHEN 'superadmin' THEN 0 WHEN 'admin' THEN 1 WHEN 'user' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        // Build a map of role_id => permission names without eager-loading the
        // relationship so we can replace it with a plain string array.
        $rolePermissions = \Spatie\Permission\Models\Role::query()
            ->join('spatie_role_has_permissions', 'spatie_roles.id', '=', 'spatie_role_has_permissions.role_id')
            ->join('spatie_permissions', 'spatie_role_has_permissions.permission_id', '=', 'spatie_permissions.id')
            ->select('spatie_roles.id', 'spatie_permissions.name')
            ->orderByRaw("CASE WHEN spatie_permissions.name LIKE 'view %' THEN 0 ELSE 1 END")
            ->orderBy('spatie_permissions.name')
            ->get()
            ->groupBy('id')
            ->map(fn ($group) => $group->pluck('name')->all());

        $roles->getCollection()->transform(function (Role $role) use ($rolePermissions) {
            $perms = $rolePermissions->get($role->id, []);

            return [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $perms,
                'users_count' => $role->users_count ?? $role->users()->count(),
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ];
        });

        $allPermissions = Permission::query()
            ->orderByRaw("CASE WHEN name LIKE 'view %' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return Inertia::render('roles/index', [
            'roles' => $roles,
            'availablePermissions' => $allPermissions,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::create([
            'name' => (string) $request->string('name'),
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($request->input('permissions', []));

        RolesChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.index');
    }

    /**
     * Store a newly created permission.
     */
    public function storePermission(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('spatie_permissions', 'name')],
        ]);

        Permission::create([
            'name' => (string) $request->string('name'),
            'guard_name' => 'web',
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission created.')]);

        return to_route('roles.index');
    }

    /**
     * Update the given role.
     */
    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        abort_if($role->name === 'superadmin', 403);

        $oldPermissions = $role->permissions->pluck('name')->sort()->values()->all();
        $newPermissions = collect($request->input('permissions', []))->sort()->values()->all();

        $role->update(['name' => (string) $request->string('name')]);
        $role->syncPermissions($newPermissions);

        // Log permission changes manually.
        if ($oldPermissions !== $newPermissions) {
            $added = collect($newPermissions)->diff($oldPermissions)->values()->all();
            $removed = collect($oldPermissions)->diff($newPermissions)->values()->all();

            $parts = [];
            if ($added !== []) {
                $parts[] = 'added '.implode(', ', $added);
            }
            if ($removed !== []) {
                $parts[] = 'removed '.implode(', ', $removed);
            }

            activity('role')
                ->performedOn($role)
                ->withProperties(['old' => $oldPermissions, 'attributes' => $newPermissions])
                ->event('updated')
                ->log('updated permissions for role "'.$role->name.'": '.implode(', ', $parts));
        }

        RolesChanged::dispatch();
        PermissionsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('roles.index');
    }

    /**
     * Delete the given role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->name === 'superadmin', 403);

        $role->delete();

        RolesChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index');
    }
}
