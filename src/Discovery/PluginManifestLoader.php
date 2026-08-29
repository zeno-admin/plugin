<?php

namespace Zeno\Plugin\Discovery;

use Composer\InstalledVersions;
use Zeno\Plugin\Definitions\MenuDefinition;
use Zeno\Plugin\Definitions\PluginDefinition;
use Zeno\Plugin\Definitions\RouteDefinition;
use Zeno\Plugin\Enums\RouteMode;
use Zeno\Plugin\Exceptions\InvalidPluginException;
use Zeno\Plugin\PluginHook;

final class PluginManifestLoader
{
    public function load(PluginDirectory $directory): PluginDefinition
    {
        $manifest = require $directory->manifestPath();

        if (! is_array($manifest)) {
            throw new InvalidPluginException("Plugin manifest [{$directory->manifestPath()}] must return an array.");
        }

        if (! is_string($manifest['name'] ?? null) || trim($manifest['name']) === '') {
            throw new InvalidPluginException('Plugin manifest name must be a non-empty string.');
        }

        $menus = $manifest['menus'] ?? [];
        $this->validateMenus($menus, $directory->key);

        $hook = $this->validateHook($manifest['hook'] ?? null);
        $this->validateFrontend($directory);
        $installed = InstalledVersions::isInstalled($directory->package);

        return new PluginDefinition(
            key: $directory->key,
            name: $manifest['name'],
            package: $directory->package,
            directory: $directory,
            version: $installed ? (InstalledVersions::getPrettyVersion($directory->package) ?? 'unknown') : 'dev-main',
            reference: $installed ? InstalledVersions::getReference($directory->package) : null,
            hook: $hook,
            routes: array_values(array_filter([
                is_file($directory->authenticatedRoutesPath())
                    ? new RouteDefinition($directory->authenticatedRoutesPath(), RouteMode::Authenticated)
                    : null,
                is_file($directory->authorizedRoutesPath())
                    ? new RouteDefinition($directory->authorizedRoutesPath(), RouteMode::Authorized)
                    : null,
            ])),
            menus: $menus,
        );
    }

    /** @return class-string<PluginHook>|null */
    private function validateHook(mixed $hook): ?string
    {
        if ($hook === null) {
            return null;
        }

        if (! is_string($hook) || ! is_subclass_of($hook, PluginHook::class)) {
            throw new InvalidPluginException('Plugin hook must extend '.PluginHook::class.'.');
        }

        return $hook;
    }

    private function validateMenus(mixed $menus, string $pluginKey): void
    {
        if (! is_array($menus) || ! array_is_list($menus)) {
            throw new InvalidPluginException('Plugin manifest menus must be a list of MenuDefinition objects.');
        }

        $validate = function (array $items) use (&$validate, $pluginKey): void {
            foreach ($items as $menu) {
                if (! $menu instanceof MenuDefinition) {
                    throw new InvalidPluginException('Plugin manifest menus must contain only MenuDefinition objects.');
                }

                if (! str_starts_with($menu->titleKey, "plugins.{$pluginKey}.")) {
                    throw new InvalidPluginException("Plugin menu title key must start with [plugins.{$pluginKey}.].");
                }

                if ($menu->routeName !== null && ! str_starts_with($menu->routeName, "{$pluginKey}.")) {
                    throw new InvalidPluginException("Plugin menu route name must start with [{$pluginKey}.].");
                }

                $validate($menu->children);
            }
        };

        $validate($menus);
    }

    private function validateFrontend(PluginDirectory $directory): void
    {
        if (! $directory->hasFrontend()) {
            return;
        }

        if (! is_file($directory->frontendEntryPath())) {
            throw new InvalidPluginException("Frontend plugin [{$directory->key}] is missing [{$directory->frontendEntryPath()}].");
        }
    }
}
