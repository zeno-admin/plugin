<?php

namespace Zeno\Plugin\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Zeno\Plugin\Definitions\MenuDefinition;

final class MenuImporter
{
    /** @param list<MenuDefinition> $menus */
    public function import(array $menus): void
    {
        DB::transaction(function () use ($menus): void {
            $this->importLevel($menus, 0);
        });
    }

    /** @param list<MenuDefinition> $menus */
    private function importLevel(array $menus, int $parentId): void
    {
        foreach ($menus as $menu) {
            $this->validateRoute($menu->routeName);

            $isLink = $menu->routeName !== null || $menu->url !== null;
            $key = $isLink
                ? [$menu->routeName !== null ? 'route_name' : 'url' => $menu->routeName ?? $menu->url]
                : ['title_key' => $menu->titleKey];
            $attributes = [
                'parent_id' => $parentId,
                'type' => $isLink ? 'link' : 'group',
                'title_key' => $menu->titleKey,
                'icon' => $menu->icon,
                'route_name' => $menu->routeName,
                'url' => ! $menu->routeName ? $menu->url : null,
                'sort' => $menu->sort,
                'visible' => true,
                'updated_at' => now(),
            ];

            DB::table('admin_menus')->updateOrInsert(
                $key,
                fn (bool $exists): array => $exists
                    ? $attributes
                    : [...$attributes, 'created_at' => now()],
            );

            $id = DB::table('admin_menus')
                ->where($key)
                ->value('id');

            $this->importLevel($menu->children, (int) $id);
        }
    }

    private function validateRoute(?string $routeName): void
    {
        if (! $routeName) {
            return;
        }

        $nameConfig = config()->string('plugin.route.name_prefix_config');
        $name = trim(config()->string($nameConfig), '.').'.'.$routeName;

        if (! Route::has($name)) {
            throw new RouteNotFoundException("Route [{$name}] not defined.");
        }
    }
}
