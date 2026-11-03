<?php

namespace App\Http\Controllers;

use App\Events\MenusChanged;
use App\Models\Menu;
use App\Models\SpatiePermission as Permission;
use App\Models\SpatieRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    /**
     * Show the menu management page.
     */
    public function index(Request $request): Response
    {
        $perPage = $request->integer('per_page', 10);

        // Children are sorted directly after their parent: group -> parent/own
        // order -> parent rows first -> child order -> name.
        $menus = Menu::query()
            ->select('menus.*')
            ->with('parent:id,name')
            ->leftJoin('menus as parent_menu', 'parent_menu.id', '=', 'menus.parent_id')
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where('menus.name', 'like', "%{$search}%");
            })
            ->orderByRaw("CASE COALESCE(parent_menu.`group`, `menus`.`group`) WHEN 'Main Menu' THEN 0 WHEN 'File Manager' THEN 1 ELSE 2 END")
            ->orderByRaw('COALESCE(parent_menu.`group`, `menus`.`group`)')
            ->orderByRaw('COALESCE(parent_menu.`order`, `menus`.`order`)')
            ->orderByRaw('CASE WHEN `menus`.`parent_id` IS NULL THEN 0 ELSE 1 END')
            ->orderBy('menus.order')
            ->orderBy('menus.name')
            ->paginate($perPage)
            ->withQueryString();

        $allPermissions = Permission::pluck('name')->all();
        $parentMenus = Menu::whereNull('parent_id')
            ->orderBy('group')
            ->orderBy('order')
            ->get(['id', 'name', 'group', 'url']);

        $menus->getCollection()->transform(function (Menu $menu) use ($allPermissions) {
            $baseName = str_replace('-', ' ', strtolower($menu->parent_id ? $menu->parent->name : $menu->name));

            return [
                ...$menu->toArray(),
                'existing_permissions' => array_values(array_filter($allPermissions, function (string $p) use ($baseName) {
                    $suffix = Str::after($p, ' ');

                    // Exact match: "view my files" matches "my files"
                    if ($baseName === $suffix) {
                        return true;
                    }

                    // Broader match: "manage files" matches "my files" (base contains suffix)
                    return $suffix !== $p && str_contains($baseName, $suffix);
                })),
            ];
        });

        return Inertia::render('menus/index', [
            'paginatedMenus' => $menus,
            'existingGroups' => Menu::distinct()->pluck('group')->filter()->values()->all(),
            'parentMenus' => $parentMenus,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created menu.
     */
    public function store(Request $request): RedirectResponse
    {
        $group = (string) $request->string('group', 'Menus');
        $order = $request->integer('order', 0);
        $parentId = $request->integer('parent_id') ?: null;

        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('menus', 'name')->where(fn ($q) => $q->where('parent_id', $parentId))],
            'group' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:menus,id'],
            'url' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'order' => [
                'required',
                'integer',
                'min:0',
                Rule::unique('menus')->where(fn ($q) => $q->where('group', $group)->where('parent_id', $parentId)),
            ],
        ]);

        $menu = Menu::create([
            'name' => (string) $request->string('name'),
            'group' => $group,
            'parent_id' => $parentId,
            'url' => (string) ($request->string('url')->toString() ?: '#'),
            'icon' => (string) $request->string('icon', ''),
            'order' => $order,
            'is_active' => true,
        ]);

        // Child menus inherit permissions from their parent; only top-level menus get permissions.
        if (! $parentId) {
            // Auto-create permissions: view + manage {menu_name}
            $baseName = str_replace('-', ' ', strtolower($menu->name));
            $viewPermission = Permission::firstOrCreate(
                ['name' => 'view '.$baseName, 'guard_name' => 'web'],
            );

            // Create manage permission only if no broader manage permission already covers this menu.
            $existingManage = Permission::where('name', 'like', 'manage %')
                ->get()
                ->first(fn (Permission $p) => str_contains($baseName, str_replace('manage ', '', $p->name)));

            $superadmin = SpatieRole::where('name', 'superadmin')->first();

            if ($superadmin) {
                $superadmin->givePermissionTo($viewPermission);
            }

            if (! $existingManage) {
                $managePermission = Permission::firstOrCreate(
                    ['name' => 'manage '.$baseName, 'guard_name' => 'web'],
                );

                if ($superadmin) {
                    $superadmin->givePermissionTo($managePermission);
                }
            }
        }

        // Auto-generate page file if URL is a real path.
        if ($menu->url && $menu->url !== '#') {
            $this->generatePageFile($menu);
        }

        MenusChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu created.')]);

        return to_route('menus.index');
    }

    /**
     * Update the given menu.
     */
    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $group = (string) $request->string('group', 'Menus');
        $order = $request->integer('order', 0);
        $parentId = $request->integer('parent_id') ?: null;

        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('menus', 'name')->where(fn ($q) => $q->where('parent_id', $parentId))->ignore($menu->id)],
            'group' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:menus,id', Rule::notIn([(string) $menu->id])],
            'url' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'order' => [
                'required',
                'integer',
                'min:0',
                Rule::unique('menus')->where(fn ($q) => $q->where('group', $group)->where('parent_id', $parentId))->ignore($menu->id),
            ],
            'is_active' => ['boolean'],
        ]);

        // Prevent cycles: the chosen parent must not be the menu itself or one of its descendants.
        if ($parentId) {
            $descendantIds = $this->descendantIds($menu);

            if ($parentId === $menu->id || in_array($parentId, $descendantIds, true)) {
                return back()->withErrors(['parent_id' => __('The selected parent is invalid.')]);
            }
        }

        $oldName = $menu->name;
        $newName = (string) $request->string('name');

        $menu->update([
            'name' => $newName,
            'group' => $group,
            'parent_id' => $parentId,
            'url' => (string) ($request->string('url')->toString() ?: '#'),
            'icon' => (string) $request->string('icon', ''),
            'order' => $order,
            'is_active' => $request->boolean('is_active', true),
        ]);

        // Update permission names if a top-level menu name changed.
        if (! $menu->parent_id && $oldName !== $newName) {
            $oldBaseName = str_replace('-', ' ', strtolower($oldName));
            $newBaseName = str_replace('-', ' ', strtolower($newName));

            foreach (['view', 'manage'] as $type) {
                $oldPermission = $type.' '.$oldBaseName;
                $newPermission = $type.' '.$newBaseName;

                $permission = Permission::where('name', $oldPermission)->first();

                if ($permission) {
                    $permission->update(['name' => $newPermission]);
                }
            }
        }

        // Auto-generate page file if URL is a real path.
        if ($menu->url && $menu->url !== '#') {
            $this->generatePageFile($menu);
        }

        MenusChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu updated.')]);

        return to_route('menus.index');
    }

    /**
     * Delete the given menu.
     */
    public function destroy(Menu $menu): RedirectResponse
    {
        $group = $menu->group;
        $deletedOrder = $menu->order;
        $parentId = $menu->parent_id;

        // Delete associated permissions (top-level menus only; children inherit).
        if (! $parentId) {
            $baseName = str_replace('-', ' ', strtolower($menu->name));
            Permission::where('name', 'view '.$baseName)->delete();
            Permission::where('name', 'manage '.$baseName)->delete();
        }

        // Delete generated page file if it exists (top-level pages only).
        if (! $parentId && $menu->url && $menu->url !== '#') {
            $slug = explode('?', ltrim($menu->url, '/'))[0];
            $pageDir = resource_path('js/pages/dynamic/'.$slug);

            if (is_dir($pageDir)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($pageDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST,
                );

                foreach ($files as $file) {
                    $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
                }

                rmdir($pageDir);
            }
        }

        $menu->delete();

        // Re-sequence orders among siblings in the same group.
        Menu::where('group', $group)
            ->where('parent_id', $parentId)
            ->where('order', '>', $deletedOrder)
            ->orderBy('order')
            ->each(fn (Menu $m) => $m->update(['order' => $m->order - 1]));

        MenusChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu deleted.')]);

        return to_route('menus.index');
    }

    /**
     * Collect all descendant IDs of the given menu (children, grandchildren, ...).
     *
     * @return array<int, int>
     */
    private function descendantIds(Menu $menu): array
    {
        $ids = [];

        foreach ($menu->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->descendantIds($child));
        }

        return $ids;
    }

    /**
     * Generate a React page file for the given menu URL.
     */
    private function generatePageFile(Menu $menu): void
    {
        $slug = explode('?', ltrim($menu->url, '/'))[0];

        if (! $slug || $slug === '#') {
            return;
        }

        $slugParts = explode('/', $slug);
        $componentName = collect($slugParts)->map(fn ($part) => str_replace('-', '', ucwords($part, '-')))->implode('');
        $filePath = resource_path('js/pages/dynamic/'.$slug.'/index.tsx');
        $directory = dirname($filePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($filePath)) {
            return;
        }

        if (count($slugParts) > 1) {
            $content = <<<'TSX'
import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { menuBreadcrumbs, showChildCrumbs } from '@/lib/menu-breadcrumbs';
import type { MenuRow } from '@/lib/menu-breadcrumbs';

export default function componentName() {
    return (
        <>
            <Head title="menuName" />
            <h1 className="sr-only">menuName</h1>
            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="menuName"
                    description="This page was automatically created from the menu."
                />
            </div>
        </>
    );
}

componentName.layout = (props: {
    menus?: MenuRow[];
    menu: { name: string; url: string };
    activeChild?: { name: string; url: string };
}) => ({
    breadcrumbs:
        showChildCrumbs(props) ??
        menuBreadcrumbs(props.menus, __MENU_URL__) ?? [
            { title: props.menu.name, href: props.menu.url },
        ],
});

TSX;
        } else {
            $content = <<<'TSX'
import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';

export default function componentName() {
    return (
        <>
            <Head title="menuName" />
            <h1 className="sr-only">menuName</h1>
            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="menuName"
                    description="This page was automatically created from the menu."
                />
            </div>
        </>
    );
}

componentName.layout = (props: { menu: { name: string; url: string } }) => ({
    breadcrumbs: [
        {
            title: props.menu.name,
            href: props.menu.url,
        },
    ],
});

TSX;
        }

        $content = str_replace('componentName', $componentName, $content);
        $content = str_replace('menuName', $menu->name, $content);
        $content = str_replace('__MENU_URL__', var_export($menu->url, true), $content);

        file_put_contents($filePath, $content);
    }
}
