<?php

namespace Zeno\Plugin\Definitions;

use Zeno\Plugin\Discovery\PluginDirectory;
use Zeno\Plugin\Exceptions\InvalidPluginException;
use Zeno\Plugin\PluginHook;

final readonly class PluginDefinition
{
    /**
     * @param  list<RouteDefinition>  $routes
     * @param  list<MenuDefinition>  $menus
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $package,
        public PluginDirectory $directory,
        public string $version = 'dev-main',
        public ?string $reference = null,
        /** @var class-string<PluginHook>|null */
        public ?string $hook = null,
        public array $routes = [],
        public array $menus = [],
    ) {
        if (! preg_match('/^[a-z][a-z0-9-]*$/', $key)) {
            throw new InvalidPluginException("Invalid plugin key [{$key}].");
        }

        foreach ($routes as $route) {
            if (! is_file($route->path)) {
                throw new InvalidPluginException("Plugin route file [{$route->path}] does not exist.");
            }
        }
    }

    public function hasTranslations(): bool
    {
        return is_dir($this->directory->translationPath());
    }

    public function translationNamespace(): ?string
    {
        return $this->hasTranslations() ? $this->key : null;
    }
}
