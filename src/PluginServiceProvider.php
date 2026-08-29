<?php

namespace Zeno\Plugin;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Zeno\Plugin\Console\Commands\PluginDisableCommand;
use Zeno\Plugin\Console\Commands\PluginEnableCommand;
use Zeno\Plugin\Console\Commands\PluginListCommand;
use Zeno\Plugin\Console\Commands\PluginMakeCommand;
use Zeno\Plugin\Console\Commands\PluginSyncCommand;
use Zeno\Plugin\Console\Commands\PluginUninstallInternalCommand;
use Zeno\Plugin\Definitions\PluginDefinition;
use Zeno\Plugin\Discovery\PluginDiscovery;
use Zeno\Plugin\Middleware\EnsurePluginEnabled;
use Zeno\Plugin\Support\AdminRouteRegistrar;
use Zeno\Plugin\Support\PluginOperationProcessor;
use Zeno\Plugin\Support\PluginRegistry;

final class PluginServiceProvider extends ServiceProvider
{
    /** @var list<PluginDefinition> */
    private array $plugins = [];

    public function register(): void
    {
        $this->app->singleton(PluginRegistry::class);

        $this->plugins = $this->app->make(PluginDiscovery::class)->discover();

        if (! $this->app->configurationIsCached()) {
            $config = $this->app->make(Repository::class);

            foreach ($this->plugins as $plugin) {
                if (is_file($plugin->directory->configPath())) {
                    $config->set(
                        "plugins.{$plugin->key}",
                        require $plugin->directory->configPath(),
                    );
                }
            }
        }
    }

    public function boot(Router $router, PluginRegistry $registry, AdminRouteRegistrar $routes): void
    {
        $router->aliasMiddleware('plugin.enabled', EnsurePluginEnabled::class);
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/plugin.php' => config_path('plugin.php'),
        ], 'zeno-plugin-config');

        $withoutSsr = [];
        $adminPrefix = trim(config()->string(config()->string('plugin.route.prefix_config')), '/');

        foreach ($this->plugins as $plugin) {
            $registry->register($plugin);

            if ($plugin->hasTranslations()) {
                $this->loadTranslationsFrom($plugin->directory->translationPath(), $plugin->key);
            }

            foreach ($plugin->migrationPaths() as $path) {
                $this->loadMigrationsFrom($path);
            }

            $routes->register($plugin);

            if ($plugin->hasFrontend()) {
                $withoutSsr[] = "{$adminPrefix}/{$plugin->key}";
                $withoutSsr[] = "{$adminPrefix}/{$plugin->key}/*";
            }
        }

        if (count($withoutSsr)) {
            Inertia::withoutSsr($withoutSsr);
        }

        Event::listen(CommandFinished::class, function (CommandFinished $event): void {
            if (
                $event->command === 'migrate'
                && $event->exitCode === 0
                && ! $event->input->getOption('pretend')
                && ! $this->app->runningUnitTests()
            ) {
                $this->app->make(PluginOperationProcessor::class)->apply();
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                PluginListCommand::class,
                PluginMakeCommand::class,
                PluginSyncCommand::class,
                PluginEnableCommand::class,
                PluginDisableCommand::class,
                PluginUninstallInternalCommand::class,
            ]);
        }
    }
}
