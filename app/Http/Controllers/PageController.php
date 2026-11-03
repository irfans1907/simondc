<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    /**
     * Render a dynamic page generated from a menu item.
     *
     * The component path is derived from the stored menu URL (not the request)
     * so a case-differing request still resolves the generated page file.
     */
    public function show(Request $request, string $slug): Response
    {
        $url = '/'.$slug;
        $menu = Menu::where('url', $url)->firstOrFail();

        $permission = 'view '.str_replace('-', ' ', strtolower($menu->name));

        if (! $request->user()->hasPermissionTo($permission)) {
            abort(403);
        }

        $managePermission = 'manage '.str_replace('-', ' ', strtolower($menu->name));
        $page = trim(explode('?', $menu->url, 2)[0], '/');

        return Inertia::render('dynamic/'.$page, [
            'menu' => [
                'id' => $menu->id,
                'name' => $menu->name,
                'group' => $menu->group,
                'url' => $menu->url,
                'icon' => $menu->icon,
            ],
            'canManage' => $request->user()->hasPermissionTo($managePermission),
        ]);
    }

    /**
     * Render a nested dynamic page generated from a child menu item.
     *
     * Fallback chain, in order:
     * 1. An "overview" child of a module with a dedicated index route but no
     *    overview route of its own redirects to that index.
     * 2. When the generated child page file exists (dynamic/{parent}/{child})
     *    it is rendered — this is the page generatePageFile() creates for
     *    every menu.
     * 3. Children of real modules without their own page redirect to the
     *    module index (never crash the parent's data page with placeholder
     *    props).
     * 4. Placeholder modules (e.g. monitoring) render the parent page with
     *    activeChild so tab-based pages keep working.
     */
    public function showChild(Request $request, string $parent, string $child): Response|RedirectResponse
    {
        $menu = Menu::where('url', '/'.$parent.'/'.$child)->firstOrFail();
        $parentMenu = Menu::where('url', '/'.$parent)->firstOrFail();

        $permission = 'view '.str_replace('-', ' ', strtolower($parentMenu->name));

        if (! $request->user()->hasPermissionTo($permission)) {
            abort(403);
        }

        $parentSlug = trim(explode('?', $parentMenu->url, 2)[0], '/');
        $childSlug = trim(explode('?', $menu->url, 2)[0], '/');

        if (strtolower(basename($childSlug)) === 'overview' && Route::has($parentSlug.'.index')) {
            return redirect()->route($parentSlug.'.index');
        }

        $managePermission = 'manage '.str_replace('-', ' ', strtolower($parentMenu->name));

        $props = [
            ...$this->childMenuProps($parentMenu, $menu),
            'canManage' => $request->user()->hasPermissionTo($managePermission),
        ];

        $childPage = resource_path('js/pages/dynamic/'.$childSlug);

        if (is_dir($childPage) || file_exists($childPage.'.tsx')) {
            return Inertia::render('dynamic/'.$childSlug, $props);
        }

        if (Route::has($parentSlug.'.index')) {
            return redirect()->route($parentSlug.'.index');
        }

        return Inertia::render('dynamic/'.$parentSlug, $props);
    }
}
