<?php

namespace Zeno\Plugin\Support;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Zeno\Plugin\Definitions\PluginDefinition;
use Zeno\Plugin\Definitions\RouteDefinition;
use Zeno\Plugin\Exceptions\InvalidPluginException;

final readonly class AdminRouteRegistrar
{
    public function __construct(
        private Application $application,
        private Router $router,
    ) {}

    public function register(PluginDefinition $plugin): void
    {
        if ($this->application->routesAreCached()) {
            return;
        }

        foreach ($plugin->routes as $routes) {
            $this->registerRoutes($plugin, $routes);
        }
    }

    private function registerRoutes(PluginDefinition $plugin, RouteDefinition $routes): void
    {
        $prefixConfig = config()->string('plugin.route.prefix_config');
        $namePrefixConfig = config()->string('plugin.route.name_prefix_config');
        $adminPrefix = trim(config()->string($prefixConfig), '/');
        $adminName = trim(config()->string($namePrefixConfig), '.');
        $namePrefix = "{$adminName}.{$plugin->key}.";
        $knownRouteIds = [];
        $knownNames = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $knownRouteIds[spl_object_id($route)] = true;

            if ($route->getName() !== null) {
                $knownNames[$route->getName()] = true;
            }
        }

        $this->router
            ->prefix("{$adminPrefix}/{$plugin->key}")
            ->name($namePrefix)
            ->middleware($this->middleware($plugin->key, $routes))
            ->group($routes->path);

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if (isset($knownRouteIds[spl_object_id($route)])) {
                continue;
            }

            $this->validateRoute($plugin, $route, $namePrefix, $knownNames);
            $knownNames[$route->getName()] = true;
        }
    }

    /** @param array<string, true> $knownNames */
    private function validateRoute(PluginDefinition $plugin, Route $route, string $namePrefix, array $knownNames): void
    {
        $uses = $route->getAction('uses');

        if ($uses instanceof Closure) {
            throw new InvalidPluginException("Plugin [{$plugin->key}] route [{$route->uri()}] must use a controller action.");
        }

        $name = $route->getName();

        if ($name === null || ! str_starts_with($name, $namePrefix)) {
            throw new InvalidPluginException("Plugin [{$plugin->key}] route [{$route->uri()}] must have a name under [{$namePrefix}].");
        }

        if (isset($knownNames[$name])) {
            throw new InvalidPluginException("Plugin route name [{$name}] is duplicated.");
        }
    }

    /** @return list<string> */
    private function middleware(string $plugin, RouteDefinition $routes): array
    {
        $beforePlugin = config()->array('plugin.route.middleware.before_plugin');
        $afterPlugin = config()->array('plugin.route.middleware.after_plugin');
        $routeMode = config()->array("plugin.route.middleware.{$routes->mode->value}");

        return array_values(array_filter([
            ...$beforePlugin,
            "plugin.enabled:{$plugin}",
            ...$afterPlugin,
            ...$routeMode,
        ], fn (mixed $middleware): bool => is_string($middleware) && $middleware !== ''));
    }
}
