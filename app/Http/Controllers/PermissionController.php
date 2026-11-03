<?php

namespace App\Http\Controllers;

use App\Events\PermissionsChanged;
use App\Models\SpatiePermission as Permission;
use App\Models\SpatieRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    /**
     * Show the permission management page.
     */
    public function index(Request $request): Response
    {
        $perPage = $request->integer('per_page', 10);

        $permissions = Permission::query()
            ->withCount('roles')
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhereHas('roles', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByRaw("CASE WHEN name LIKE 'view %' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        // Build a map of permission_id => role names.
        $permissionRoles = Role::query()
            ->join('spatie_role_has_permissions', 'spatie_roles.id', '=', 'spatie_role_has_permissions.role_id')
            ->join('spatie_permissions', 'spatie_role_has_permissions.permission_id', '=', 'spatie_permissions.id')
            ->select('spatie_permissions.id', 'spatie_roles.name')
            ->get()
            ->groupBy('id')
            ->map(fn ($group) => $group->pluck('name')->all());

        $permissions->getCollection()->transform(function (Permission $permission) use ($permissionRoles) {
            $roles = $permissionRoles->get($permission->id, []);

            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
                'roles' => $roles,
                'roles_count' => count($roles),
                'created_at' => $permission->created_at,
                'updated_at' => $permission->updated_at,
            ];
        });

        return Inertia::render('permissions/index', [
            'permissions' => $permissions,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created permission.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('spatie_permissions', 'name')],
        ]);

        Permission::create([
            'name' => (string) $request->string('name'),
            'guard_name' => 'web',
        ]);

        // Auto-assign new permission to superadmin.
        $superadmin = SpatieRole::where('name', 'superadmin')->first();

        if ($superadmin) {
            $superadmin->givePermissionTo((string) $request->string('name'));
        }

        PermissionsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission created.')]);

        return to_route('permissions.index');
    }

    /**
     * Update the given permission.
     */
    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('spatie_permissions', 'name')->ignore($permission->id)],
        ]);

        $permission->update([
            'name' => (string) $request->string('name'),
        ]);

        PermissionsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission updated.')]);

        return to_route('permissions.index');
    }

    /**
     * Delete the given permission.
     */
    public function destroy(Permission $permission): RedirectResponse
    {
        $permission->delete();

        PermissionsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission deleted.')]);

        return to_route('permissions.index');
    }
}
