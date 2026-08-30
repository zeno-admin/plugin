<?php

namespace Zeno\Plugin\Support;

use Illuminate\Contracts\Container\Container;
use Throwable;
use Zeno\Plugin\Definitions\PluginDefinition;
use Zeno\Plugin\Enums\PluginStatus;
use Zeno\Plugin\Exceptions\PluginHookException;
use Zeno\Plugin\Exceptions\PluginNotSyncedException;
use Zeno\Plugin\Exceptions\PluginOperationException;
use Zeno\Plugin\Models\AdminPlugin;
use Zeno\Plugin\PluginHook;

final readonly class PluginManager
{
    public function __construct(
        private PluginRegistry $plugins,
        private MenuImporter $menus,
        private Container $container,
    ) {}

    public function sync(string $key): AdminPlugin
    {
        $plugin = $this->plugins->get($key);
        $record = $this->record($key);

        $this->menus->import($plugin->menus);

        return $record->refresh();
    }

    public function install(string $key): AdminPlugin
    {
        $plugin = $this->plugins->get($key);
        $record = AdminPlugin::query()->find($key);

        if ($record !== null && $record->status !== PluginStatus::Uninstalled) {
            return $record;
        }

        $this->menus->import($plugin->menus);
        $this->runHook($plugin, 'install');

        return AdminPlugin::query()->updateOrCreate(['key' => $key], [
            'version' => $plugin->version,
            'reference' => $plugin->reference,
            'status' => PluginStatus::Disabled,
        ]);
    }

    public function enable(string $key): AdminPlugin
    {
        $plugin = $this->plugins->get($key);
        $record = $this->record($key);
        $this->ensureInstalled($record);

        if ($record->status === PluginStatus::Enabled) {
            return $record;
        }

        $this->runHook($plugin, 'enable');
        $record->update(['status' => PluginStatus::Enabled]);

        return $record->refresh();
    }

    public function disable(string $key): AdminPlugin
    {
        $plugin = $this->plugins->get($key);
        $record = $this->record($key);
        $this->ensureInstalled($record);

        if ($record->status === PluginStatus::Disabled) {
            return $record;
        }

        $this->runHook($plugin, 'disable');
        $record->update(['status' => PluginStatus::Disabled]);

        return $record->refresh();
    }

    public function upgrade(string $key, ?bool $restoreEnabled = null): AdminPlugin
    {
        $plugin = $this->plugins->get($key);
        $record = $this->record($key);

        if ($this->matchesRelease($record, $plugin)) {
            return $record;
        }

        $restoreEnabled ??= $record->status === PluginStatus::Enabled;

        if ($record->status === PluginStatus::Enabled) {
            $this->disable($key);
        }

        $fromVersion = $record->version;
        $this->runHook($plugin, 'upgrade', [$fromVersion, $plugin->version]);
        $this->menus->import($plugin->menus);

        if ($restoreEnabled) {
            $this->enable($key);
        }

        $record->update([
            'version' => $plugin->version,
            'reference' => $plugin->reference,
        ]);

        return $record->refresh();
    }

    public function uninstall(string $key): void
    {
        $plugin = $this->plugins->get($key);
        $record = $this->record($key);

        if ($record->status === PluginStatus::Uninstalled) {
            return;
        }

        if ($record->status === PluginStatus::Enabled) {
            $this->disable($key);
        }

        $this->runHook($plugin, 'uninstall');
        $record->update(['status' => PluginStatus::Uninstalled]);
    }

    private function record(string $key): AdminPlugin
    {
        return AdminPlugin::query()->find($key) ?? throw new PluginNotSyncedException($key);
    }

    private function matchesRelease(AdminPlugin $record, PluginDefinition $plugin): bool
    {
        return $record->version === $plugin->version
            && $record->reference === $plugin->reference;
    }

    private function ensureInstalled(AdminPlugin $record): void
    {
        if ($record->status === PluginStatus::Uninstalled) {
            throw new PluginOperationException("Plugin [{$record->key}] must be installed first.");
        }
    }

    /** @param list<mixed> $arguments */
    private function runHook(PluginDefinition $plugin, string $method, array $arguments = []): void
    {
        if ($plugin->hook === null) {
            return;
        }

        try {
            $hook = $this->container->make($plugin->hook);

            if (! $hook instanceof PluginHook) {
                return;
            }

            $hook->{$method}(...$arguments);
        } catch (Throwable $exception) {
            throw new PluginHookException($plugin->key, $method, $exception);
        }
    }
}
