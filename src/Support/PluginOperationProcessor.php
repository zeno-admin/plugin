<?php

namespace Zeno\Plugin\Support;

use Illuminate\Support\Facades\Schema;
use Throwable;
use Zeno\Plugin\Enums\PluginStatus;
use Zeno\Plugin\Exceptions\PluginOperationException;
use Zeno\Plugin\Models\AdminPlugin;

final class PluginOperationProcessor
{
    public function __construct(
        private readonly PendingPluginOperations $operations,
        private readonly PluginManager $plugins,
        private readonly PluginRegistry $registry,
    ) {}

    public function apply(): int
    {
        if (! $this->hasLifecycleTables()) {
            return 0;
        }

        $processed = 0;

        foreach ($this->operations->read() as $package => $operation) {
            if (! is_string($package) || ! is_array($operation)) {
                throw new PluginOperationException('Pending plugin operation must be an object keyed by package name.');
            }

            $applied = $this->applyOperation($package, $operation);
            $this->operations->forget($package, $applied);
            $processed++;
        }

        return $processed;
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function applyOperation(string $package, array $operation): array
    {
        $operationPackage = $operation['package'] ?? null;
        $key = $operation['key'] ?? null;
        $stage = $operation['stage'] ?? null;

        if (
            $operationPackage !== $package
            || ! is_string($key) || $key === ''
            || ! is_string($stage) || ! in_array($stage, ['pending', 'installing', 'installed'], true)
        ) {
            throw new PluginOperationException('Pending plugin operation has an invalid package, key, or stage.');
        }

        $plugin = $this->registry->get($key);

        if ($plugin->package !== $package) {
            throw new PluginOperationException("Pending plugin package [{$package}] does not match plugin [{$key}].");
        }

        $record = AdminPlugin::query()->find($key);

        if (
            in_array($stage, ['installing', 'installed'], true)
            || $record === null
            || $record->status === PluginStatus::Uninstalled
        ) {
            return $this->install($operation, $record);
        }

        if ($record->version === $plugin->version && $record->reference === $plugin->reference) {
            return $operation;
        }

        if (! array_key_exists('restore_enabled', $operation)) {
            $operation['restore_enabled'] = $record->status->value === 'enabled';
            $this->operations->put($operation);
        }

        $this->plugins->upgrade($key, (bool) $operation['restore_enabled']);

        return $operation;
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function install(array $operation, ?AdminPlugin $record): array
    {
        $stage = (string) $operation['stage'];
        $key = (string) $operation['key'];

        if ($stage === 'pending') {
            $operation['stage'] = 'installing';
            $this->operations->put($operation);
            $stage = 'installing';
        }

        if ($stage === 'installing') {
            if ($record === null || $record->status === PluginStatus::Uninstalled) {
                $this->plugins->install($key);
            }

            $operation['stage'] = 'installed';
            $this->operations->put($operation);
        }

        $this->plugins->enable($key);

        return $operation;
    }

    private function hasLifecycleTables(): bool
    {
        try {
            return Schema::hasTable('migrations')
                && Schema::hasTable('admin_plugins')
                && Schema::hasTable('admin_menus');
        } catch (Throwable $exception) {
            throw new PluginOperationException(
                'Unable to inspect the plugin lifecycle database: '.$exception->getMessage(),
                previous: $exception,
            );
        }
    }
}
