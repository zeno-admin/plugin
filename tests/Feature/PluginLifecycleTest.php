<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Tests\TestCase;
use Zeno\Plugin\Definitions\MenuDefinition;
use Zeno\Plugin\Definitions\PluginDefinition;
use Zeno\Plugin\Discovery\PluginDirectory;
use Zeno\Plugin\Discovery\PluginManifestLoader;
use Zeno\Plugin\Enums\PluginStatus;
use Zeno\Plugin\Exceptions\InvalidPluginException;
use Zeno\Plugin\Exceptions\PluginHookException;
use Zeno\Plugin\Exceptions\PluginOperationException;
use Zeno\Plugin\Models\AdminPlugin;
use Zeno\Plugin\Support\MenuImporter;
use Zeno\Plugin\Support\PendingPluginOperations;
use Zeno\Plugin\Support\PluginManager;
use Zeno\Plugin\Support\PluginOperationProcessor;
use Zeno\Plugin\Support\PluginRegistry;
use Zeno\Plugin\Tests\Fixtures\HookRecorder;
use Zeno\Plugin\Tests\Fixtures\RecordingHook;

require_once __DIR__.'/../Fixtures/HookRecorder.php';
require_once __DIR__.'/../Fixtures/RecordingHook.php';

uses(TestCase::class, RefreshDatabase::class);

function lifecyclePluginDefinition(
    string $version = '1.0.0',
    ?string $reference = 'reference-1',
    ?string $hook = RecordingHook::class,
): PluginDefinition {
    return new PluginDefinition(
        key: 'acme-lifecycle',
        name: 'Lifecycle fixture',
        package: 'acme/lifecycle',
        directory: new PluginDirectory(
            key: 'acme-lifecycle',
            package: 'acme/lifecycle',
            path: base_path('packages/plugin/tests/Fixtures'),
        ),
        version: $version,
        reference: $reference,
        hook: $hook,
        menus: [new MenuDefinition(
            titleKey: 'plugins.acme-lifecycle.nav.index',
            url: 'https://example.com/acme-lifecycle',
        )],
    );
}

/** @return array{PluginManager, PluginRegistry} */
function lifecyclePluginManager(PluginDefinition $plugin): array
{
    $registry = new PluginRegistry;
    $registry->register($plugin);

    return [
        new PluginManager(
            $registry,
            app(MenuImporter::class),
            app(),
        ),
        $registry,
    ];
}

beforeEach(function () {
    $this->recorder = new HookRecorder;
    app()->instance(HookRecorder::class, $this->recorder);
});

it('installs and enables a plugin through its hook', function () {
    [$manager] = lifecyclePluginManager(lifecyclePluginDefinition());

    $installed = $manager->install('acme-lifecycle');

    expect($installed->status)->toBe(PluginStatus::Disabled)
        ->and($installed->version)->toBe('1.0.0')
        ->and($installed->reference)->toBe('reference-1')
        ->and($this->recorder->events)->toBe(['install'])
        ->and(DB::table('admin_menus')->where('title_key', 'plugins.acme-lifecycle.nav.index')->exists())
        ->toBeTrue();

    expect($manager->enable('acme-lifecycle')->status)->toBe(PluginStatus::Enabled)
        ->and($this->recorder->events)->toBe(['install', 'enable']);
});

it('supports plugins without a hook', function () {
    [$manager] = lifecyclePluginManager(lifecyclePluginDefinition(hook: null));

    expect($manager->install('acme-lifecycle')->status)->toBe(PluginStatus::Disabled)
        ->and($manager->enable('acme-lifecycle')->status)->toBe(PluginStatus::Enabled)
        ->and($manager->disable('acme-lifecycle')->status)->toBe(PluginStatus::Disabled);
});

it('keeps install retryable when the install hook fails', function () {
    [$manager] = lifecyclePluginManager(lifecyclePluginDefinition());
    $this->recorder->failOn = 'install';

    expect(fn () => $manager->install('acme-lifecycle'))
        ->toThrow(PluginHookException::class, 'install hook failed')
        ->and(AdminPlugin::query()->find('acme-lifecycle'))->toBeNull()
        ->and(DB::table('admin_menus')->where('title_key', 'plugins.acme-lifecycle.nav.index')->exists())
        ->toBeTrue();

    $this->recorder->failOn = null;

    expect($manager->install('acme-lifecycle')->status)->toBe(PluginStatus::Disabled)
        ->and($this->recorder->events)->toBe(['install', 'install']);
});

it('does not run install hooks or create state when menu import fails', function () {
    $plugin = lifecyclePluginDefinition();
    $plugin = new PluginDefinition(
        key: $plugin->key,
        name: $plugin->name,
        package: $plugin->package,
        directory: $plugin->directory,
        version: $plugin->version,
        reference: $plugin->reference,
        hook: $plugin->hook,
        menus: [new MenuDefinition(
            titleKey: 'plugins.acme-lifecycle.nav.missing',
            routeName: 'acme-lifecycle.missing',
        )],
    );
    [$manager] = lifecyclePluginManager($plugin);

    expect(fn () => $manager->install($plugin->key))
        ->toThrow(RouteNotFoundException::class)
        ->and(AdminPlugin::query()->find($plugin->key))->toBeNull()
        ->and($this->recorder->events)->toBe([]);
});

it('changes status only after enable and disable hooks succeed', function () {
    [$manager] = lifecyclePluginManager(lifecyclePluginDefinition());
    $manager->install('acme-lifecycle');
    $this->recorder->failOn = 'enable';

    expect(fn () => $manager->enable('acme-lifecycle'))->toThrow(PluginHookException::class)
        ->and(AdminPlugin::query()->findOrFail('acme-lifecycle')->status)->toBe(PluginStatus::Disabled);

    $this->recorder->failOn = null;
    $manager->enable('acme-lifecycle');
    $this->recorder->failOn = 'disable';

    expect(fn () => $manager->disable('acme-lifecycle'))->toThrow(PluginHookException::class)
        ->and(AdminPlugin::query()->findOrFail('acme-lifecycle')->status)->toBe(PluginStatus::Enabled);
});

it('does not repeat hooks for unchanged states', function () {
    [$manager] = lifecyclePluginManager(lifecyclePluginDefinition());
    $manager->install('acme-lifecycle');
    $manager->enable('acme-lifecycle');
    $manager->enable('acme-lifecycle');
    $manager->disable('acme-lifecycle');
    $manager->disable('acme-lifecycle');

    expect($this->recorder->events)->toBe(['install', 'enable', 'disable']);
});

it('syncs only menus without changing release status or hooks', function () {
    [$manager] = lifecyclePluginManager(lifecyclePluginDefinition());
    $manager->install('acme-lifecycle');
    $manager->enable('acme-lifecycle');

    [$syncer] = lifecyclePluginManager(lifecyclePluginDefinition('2.0.0', 'reference-2'));
    $record = $syncer->sync('acme-lifecycle');

    expect($record->status)->toBe(PluginStatus::Enabled)
        ->and($record->version)->toBe('1.0.0')
        ->and($record->reference)->toBe('reference-1')
        ->and($this->recorder->events)->toBe(['install', 'enable']);
});

it('upgrades and restores an enabled plugin', function () {
    [$manager] = lifecyclePluginManager(lifecyclePluginDefinition());
    $manager->install('acme-lifecycle');
    $manager->enable('acme-lifecycle');

    [$upgrader] = lifecyclePluginManager(lifecyclePluginDefinition('2.0.0', 'reference-2'));
    $record = $upgrader->upgrade('acme-lifecycle');

    expect($record->status)->toBe(PluginStatus::Enabled)
        ->and($record->version)->toBe('2.0.0')
        ->and($record->reference)->toBe('reference-2')
        ->and($this->recorder->events)->toBe([
            'install',
            'enable',
            'disable',
            'upgrade:1.0.0:2.0.0',
            'enable',
        ]);
});

it('preserves a disabled state during upgrade', function () {
    [$manager] = lifecyclePluginManager(lifecyclePluginDefinition());
    $manager->install('acme-lifecycle');

    [$upgrader] = lifecyclePluginManager(lifecyclePluginDefinition('2.0.0', 'reference-2'));

    expect($upgrader->upgrade('acme-lifecycle')->status)->toBe(PluginStatus::Disabled)
        ->and($this->recorder->events)->toBe(['install', 'upgrade:1.0.0:2.0.0']);
});

it('keeps the old release and disabled state when upgrade fails', function () {
    [$manager] = lifecyclePluginManager(lifecyclePluginDefinition());
    $manager->install('acme-lifecycle');
    $manager->enable('acme-lifecycle');

    [$upgrader] = lifecyclePluginManager(lifecyclePluginDefinition('2.0.0', 'reference-2'));
    $this->recorder->failOn = 'upgrade:1.0.0:2.0.0';

    expect(fn () => $upgrader->upgrade('acme-lifecycle'))->toThrow(PluginHookException::class)
        ->and(AdminPlugin::query()->findOrFail('acme-lifecycle')->status)->toBe(PluginStatus::Disabled)
        ->and(AdminPlugin::query()->findOrFail('acme-lifecycle')->version)->toBe('1.0.0')
        ->and(AdminPlugin::query()->findOrFail('acme-lifecycle')->reference)->toBe('reference-1');
});

it('runs uninstall hooks while preserving menu and business data', function () {
    [$manager] = lifecyclePluginManager(lifecyclePluginDefinition());
    $manager->install('acme-lifecycle');
    $manager->enable('acme-lifecycle');
    DB::table('cache')->insert([
        'key' => 'acme-lifecycle-business-data',
        'value' => 'preserved',
        'expiration' => now()->addDay()->timestamp,
    ]);

    $manager->uninstall('acme-lifecycle');

    expect(AdminPlugin::query()->findOrFail('acme-lifecycle')->status)->toBe(PluginStatus::Uninstalled)
        ->and(DB::table('admin_menus')->where('title_key', 'plugins.acme-lifecycle.nav.index')->exists())
        ->toBeTrue()
        ->and(DB::table('cache')->where('key', 'acme-lifecycle-business-data')->value('value'))
        ->toBe('preserved')
        ->and($this->recorder->events)->toBe(['install', 'enable', 'disable', 'uninstall']);
});

it('reinstalls a preserved uninstalled plugin record', function () {
    [$manager] = lifecyclePluginManager(lifecyclePluginDefinition());
    $manager->install('acme-lifecycle');
    $manager->enable('acme-lifecycle');
    $manager->uninstall('acme-lifecycle');

    expect($manager->install('acme-lifecycle')->status)->toBe(PluginStatus::Disabled)
        ->and($manager->enable('acme-lifecycle')->status)->toBe(PluginStatus::Enabled)
        ->and($this->recorder->events)->toBe([
            'install',
            'enable',
            'disable',
            'uninstall',
            'install',
            'enable',
        ]);
});

it('aborts uninstall and preserves state when its hook fails', function () {
    [$manager] = lifecyclePluginManager(lifecyclePluginDefinition());
    $manager->install('acme-lifecycle');
    $manager->enable('acme-lifecycle');
    $this->recorder->failOn = 'uninstall';

    expect(fn () => $manager->uninstall('acme-lifecycle'))->toThrow(PluginHookException::class)
        ->and(AdminPlugin::query()->findOrFail('acme-lifecycle')->status)->toBe(PluginStatus::Disabled);
});

it('validates hook classes declared by manifests', function () {
    $manifest = base_path('packages/plugin/tests/Fixtures/invalid-hook-plugin');
    $directory = new PluginDirectory('invalid-hook', 'acme/invalid-hook', $manifest);

    expect(fn () => app(PluginManifestLoader::class)->load($directory))
        ->toThrow(InvalidPluginException::class, 'must extend');
});

it('applies pending install operations and resumes failed enables', function () {
    $plugin = lifecyclePluginDefinition();
    [$manager, $registry] = lifecyclePluginManager($plugin);
    $path = storage_path('framework/testing/zeno-plugin-pending.json');
    $operations = new PendingPluginOperations($path);
    $operations->put([
        'package' => $plugin->package,
        'key' => $plugin->key,
        'stage' => 'pending',
    ]);
    $processor = new PluginOperationProcessor($operations, $manager, $registry);
    $this->recorder->failOn = 'enable';

    expect(fn () => $processor->apply())->toThrow(PluginHookException::class)
        ->and(AdminPlugin::query()->findOrFail($plugin->key)->status)->toBe(PluginStatus::Disabled)
        ->and($operations->read()[$plugin->package]['stage'])->toBe('installed');

    $this->recorder->failOn = null;

    expect($processor->apply())->toBe(1)
        ->and(AdminPlugin::query()->findOrFail($plugin->key)->status)->toBe(PluginStatus::Enabled)
        ->and($operations->read())->toBe([]);
});

it('resumes an install interrupted before its installed stage is persisted', function () {
    $plugin = lifecyclePluginDefinition();
    [$manager, $registry] = lifecyclePluginManager($plugin);
    $manager->install($plugin->key);
    $path = storage_path('framework/testing/zeno-plugin-installing.json');
    $operations = new PendingPluginOperations($path);
    $operations->put([
        'package' => $plugin->package,
        'key' => $plugin->key,
        'stage' => 'installing',
    ]);

    expect((new PluginOperationProcessor($operations, $manager, $registry))->apply())->toBe(1)
        ->and(AdminPlugin::query()->findOrFail($plugin->key)->status)->toBe(PluginStatus::Enabled)
        ->and($operations->read())->toBe([])
        ->and($this->recorder->events)->toBe(['install', 'enable']);
});

it('preserves an existing disabled plugin during deployment reconciliation', function () {
    $plugin = lifecyclePluginDefinition();
    [$manager, $registry] = lifecyclePluginManager($plugin);
    $manager->install($plugin->key);
    $path = storage_path('framework/testing/zeno-plugin-existing.json');
    $operations = new PendingPluginOperations($path);
    $operations->put([
        'package' => $plugin->package,
        'key' => $plugin->key,
        'stage' => 'pending',
    ]);

    expect((new PluginOperationProcessor($operations, $manager, $registry))->apply())->toBe(1)
        ->and(AdminPlugin::query()->findOrFail($plugin->key)->status)->toBe(PluginStatus::Disabled)
        ->and($this->recorder->events)->toBe(['install']);
});

it('upgrades when only the Composer reference changes', function () {
    [$manager] = lifecyclePluginManager(lifecyclePluginDefinition());
    $manager->install('acme-lifecycle');

    [$upgrader] = lifecyclePluginManager(lifecyclePluginDefinition('1.0.0', 'reference-2'));

    expect($upgrader->upgrade('acme-lifecycle')->reference)->toBe('reference-2')
        ->and($this->recorder->events)->toBe(['install', 'upgrade:1.0.0:1.0.0']);
});

it('applies pending upgrades and restores the previous enabled state', function () {
    $versionOne = lifecyclePluginDefinition();
    [$manager] = lifecyclePluginManager($versionOne);
    $manager->install($versionOne->key);
    $manager->enable($versionOne->key);

    $versionTwo = lifecyclePluginDefinition('2.0.0', 'reference-2');
    [$upgrader, $registry] = lifecyclePluginManager($versionTwo);
    $path = storage_path('framework/testing/zeno-plugin-upgrade.json');
    $operations = new PendingPluginOperations($path);
    $operations->put([
        'package' => $versionTwo->package,
        'key' => $versionTwo->key,
        'stage' => 'pending',
    ]);

    expect((new PluginOperationProcessor($operations, $upgrader, $registry))->apply())->toBe(1)
        ->and(AdminPlugin::query()->findOrFail($versionTwo->key)->status)->toBe(PluginStatus::Enabled)
        ->and(AdminPlugin::query()->findOrFail($versionTwo->key)->version)->toBe('2.0.0')
        ->and($operations->read())->toBe([]);
});

it('keeps failed pending upgrades with their original enabled state', function () {
    $versionOne = lifecyclePluginDefinition();
    [$manager] = lifecyclePluginManager($versionOne);
    $manager->install($versionOne->key);
    $manager->enable($versionOne->key);

    $versionTwo = lifecyclePluginDefinition('2.0.0', 'reference-2');
    [$upgrader, $registry] = lifecyclePluginManager($versionTwo);
    $path = storage_path('framework/testing/zeno-plugin-upgrade-failure.json');
    $operations = new PendingPluginOperations($path);
    $operations->put([
        'package' => $versionTwo->package,
        'key' => $versionTwo->key,
        'stage' => 'pending',
    ]);
    $this->recorder->failOn = 'upgrade:1.0.0:2.0.0';

    expect(fn () => (new PluginOperationProcessor($operations, $upgrader, $registry))->apply())
        ->toThrow(PluginHookException::class)
        ->and($operations->read()[$versionTwo->package]['restore_enabled'])->toBeTrue()
        ->and(AdminPlugin::query()->findOrFail($versionTwo->key)->status)->toBe(PluginStatus::Disabled)
        ->and(AdminPlugin::query()->findOrFail($versionTwo->key)->version)->toBe('1.0.0');
});

it('rejects malformed and corrupt pending operations without losing them', function () {
    $plugin = lifecyclePluginDefinition();
    [$manager, $registry] = lifecyclePluginManager($plugin);
    $path = storage_path('framework/testing/zeno-plugin-malformed.json');
    $operations = new PendingPluginOperations($path);
    $operations->put([
        'package' => $plugin->package,
        'key' => $plugin->key,
    ]);

    expect(fn () => (new PluginOperationProcessor($operations, $manager, $registry))->apply())
        ->toThrow(PluginOperationException::class)
        ->and($operations->read())->toHaveKey($plugin->package);

    $corruptPath = storage_path('framework/testing/zeno-plugin-corrupt.json');
    file_put_contents($corruptPath, '{invalid');

    expect(fn () => (new PendingPluginOperations($corruptPath))->read())
        ->toThrow(PluginOperationException::class, 'invalid');
});

it('rejects pending operations whose package identity is inconsistent', function (string $index, string $package) {
    $plugin = lifecyclePluginDefinition();
    [$manager, $registry] = lifecyclePluginManager($plugin);
    $path = storage_path('framework/testing/zeno-plugin-package-mismatch.json');
    file_put_contents($path, json_encode([
        $index => [
            'package' => $package,
            'key' => $plugin->key,
            'stage' => 'pending',
        ],
    ], JSON_THROW_ON_ERROR));
    $operations = new PendingPluginOperations($path);

    expect(fn () => (new PluginOperationProcessor($operations, $manager, $registry))->apply())
        ->toThrow(PluginOperationException::class)
        ->and(json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR))->toHaveKey($index);
})->with([
    'index differs from payload' => ['acme/index-mismatch', 'acme/lifecycle'],
    'payload differs from registered plugin' => ['acme/package-mismatch', 'acme/package-mismatch'],
]);

it('converts scalar pending entries to plugin operation exceptions without losing them', function () {
    $plugin = lifecyclePluginDefinition();
    [$manager, $registry] = lifecyclePluginManager($plugin);
    $path = storage_path('framework/testing/zeno-plugin-scalar.json');
    file_put_contents($path, json_encode([
        $plugin->package => 'invalid',
    ], JSON_THROW_ON_ERROR));
    $operations = new PendingPluginOperations($path);

    expect(fn () => (new PluginOperationProcessor($operations, $manager, $registry))->apply())
        ->toThrow(PluginOperationException::class)
        ->and(file_get_contents($path))->toContain('invalid');
});

it('forgets a pending operation only when it still matches the processed value', function () {
    $path = storage_path('framework/testing/zeno-plugin-conditional-forget.json');
    $operations = new PendingPluginOperations($path);
    $pending = [
        'package' => 'acme/lifecycle',
        'key' => 'acme-lifecycle',
        'stage' => 'pending',
    ];
    $replacement = [...$pending, 'stage' => 'installed'];

    $operations->put($pending);
    $operations->put($replacement);
    $operations->forget($pending['package'], $pending);

    expect($operations->read()[$pending['package']])->toBe($replacement);

    $operations->forget($replacement['package'], $replacement);

    expect($operations->read())->toBe([]);
});

it('fails pending processing on database connection errors', function () {
    $plugin = lifecyclePluginDefinition();
    [$manager, $registry] = lifecyclePluginManager($plugin);
    $path = storage_path('framework/testing/zeno-plugin-database-error.json');
    $operations = new PendingPluginOperations($path);
    $operations->put([
        'package' => $plugin->package,
        'key' => $plugin->key,
        'stage' => 'pending',
    ]);
    $original = config('database.default');
    config()->set('database.connections.lifecycle_broken', [
        'driver' => 'sqlite',
        'database' => '/missing/zeno/plugin/database.sqlite',
        'prefix' => '',
    ]);
    config()->set('database.default', 'lifecycle_broken');
    DB::purge('lifecycle_broken');

    try {
        expect(fn () => (new PluginOperationProcessor($operations, $manager, $registry))->apply())
            ->toThrow(PluginOperationException::class, 'Unable to inspect')
            ->and($operations->read())->toHaveKey($plugin->package);
    } finally {
        config()->set('database.default', $original);
        DB::purge('lifecycle_broken');
    }
});
