<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;
use Zeno\Plugin\Definitions\MenuDefinition;
use Zeno\Plugin\Definitions\PluginDefinition;
use Zeno\Plugin\Discovery\PluginDirectory;
use Zeno\Plugin\Discovery\PluginManifestLoader;
use Zeno\Plugin\Enums\PluginStatus;
use Zeno\Plugin\Exceptions\InvalidPluginException;
use Zeno\Plugin\Models\AdminPlugin;
use Zeno\Plugin\Support\PluginRegistry;
use Zeno\Plugin\Support\PluginState;
use Zeno\Plugin\Support\PluginTranslations;

uses(TestCase::class, RefreshDatabase::class);

it('generates plugins with flat menu title keys', function () {
    $originalBasePath = base_path();
    $basePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zeno-plugin-menu-'.Str::random(12);
    File::makeDirectory($basePath);
    app()->setBasePath($basePath);

    try {
        $exitCode = Artisan::call('zeno:plugin:make', [
            'package' => 'acme/tickets',
        ]);
        $plugin = $basePath.'/packages/tickets';
        $manifest = File::get($plugin.'/plugin.php');
        $english = require $plugin.'/lang/en/admin.php';

        expect($exitCode)->toBe(0)
            ->and($manifest)->toContain(
                "titleKey: 'plugins.tickets.menu_titles.groups.root'",
                "titleKey: 'plugins.tickets.menu_titles.links.index'",
            )
            ->and($english['plugins']['tickets']['menu_titles'])->toBe([
                'groups' => ['root' => 'Tickets'],
                'links' => ['index' => 'Overview'],
            ]);
    } finally {
        app()->setBasePath($originalBasePath);
        File::deleteDirectory($basePath);
    }
});

it('shares enabled plugin menu titles from the flat translation maps', function () {
    $directory = new PluginDirectory(
        key: 'menu-title-plugin',
        package: 'acme/menu-title-plugin',
        path: base_path('packages/plugin/tests/Fixtures/menu-title-plugin'),
    );
    $plugin = new PluginDefinition(
        key: 'menu-title-plugin',
        name: 'Menu Title Plugin',
        package: 'acme/menu-title-plugin',
        directory: $directory,
        menus: [
            new MenuDefinition(
                titleKey: 'plugins.menu-title-plugin.menu_titles.groups.root',
                children: [
                    new MenuDefinition(
                        titleKey: 'plugins.menu-title-plugin.menu_titles.links.index',
                        url: '/menu-title-plugin',
                    ),
                ],
            ),
        ],
    );
    $plugins = new PluginRegistry;
    $plugins->register($plugin);
    app('translator')->addNamespace('menu-title-plugin', $directory->translationPath());
    AdminPlugin::create([
        'key' => 'menu-title-plugin',
        'version' => '1.0.0',
        'status' => PluginStatus::Enabled,
    ]);
    $resolver = new PluginTranslations(
        $plugins,
        app(PluginState::class),
        app('translator'),
    );

    $translations = $resolver->resolve(
        Request::create('/'),
        [],
        fn (): array => [],
    );

    expect($translations)->toHaveKey(
        'plugins.menu-title-plugin.menu_titles.groups.root',
        'Menu Title Plugin',
    )->toHaveKey(
        'plugins.menu-title-plugin.menu_titles.links.index',
        'Overview',
    );
});

it('rejects plugin menu title keys that do not match their menu type', function (string $key, string $expectedPrefix) {
    $path = base_path("packages/plugin/tests/Fixtures/{$key}");
    $directory = new PluginDirectory($key, "acme/{$key}", $path);

    expect(fn () => app(PluginManifestLoader::class)->load($directory))
        ->toThrow(InvalidPluginException::class, "must start with [{$expectedPrefix}]");
})->with([
    'group using a link key' => [
        'invalid-group-menu-key',
        'plugins.invalid-group-menu-key.menu_titles.groups.',
    ],
    'link using a group key' => [
        'invalid-link-menu-key',
        'plugins.invalid-link-menu-key.menu_titles.links.',
    ],
]);
