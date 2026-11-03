<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;

abstract class Controller
{
    /**
     * Shared menu props for child pages, matching PageController::showChild's
     * contract so breadcrumb layouts can render a clickable parent crumb from
     * server data. Returns an empty array when either menu row is missing so
     * callers keep their fallbacks.
     *
     * @return array<string, mixed>
     */
    protected function childMenuProps(?Menu $parentMenu, ?Menu $childMenu): array
    {
        if (! $parentMenu || ! $childMenu) {
            return [];
        }

        return [
            'menu' => [
                'id' => $parentMenu->id,
                'name' => $parentMenu->name,
                'group' => $parentMenu->group,
                'url' => $parentMenu->url,
                'icon' => $parentMenu->icon,
            ],
            'activeChild' => [
                'id' => $childMenu->id,
                'name' => $childMenu->name,
                'url' => $childMenu->url,
            ],
        ];
    }

    /**
     * Find the ids of every category whose name matches exactly, plus all of
     * their descendants. Thin delegate to Category::idsByName(), which also
     * serves contexts without a controller (e.g. CustomerRequest).
     *
     * @return list<int>
     */
    protected function categoryIdsByName(string $name): array
    {
        return Category::idsByName($name);
    }
}
