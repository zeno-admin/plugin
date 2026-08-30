<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->originalBasePath = base_path();
    $this->pluginMakeBasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zeno-plugin-make-'.Str::random(12);
    File::makeDirectory($this->pluginMakeBasePath);
    $this->app->setBasePath($this->pluginMakeBasePath);
});

afterEach(function () {
    $this->app->setBasePath($this->originalBasePath);
    File::deleteDirectory($this->pluginMakeBasePath);
});

it('creates a plugin from the fixed stubs', function () {
    $exitCode = Artisan::call('zeno:plugin:make', [
        'package' => 'acme/tickets',
    ]);

    $plugin = $this->pluginMakeBasePath.'/packages/tickets';
    $composer = json_decode(File::get($plugin.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $files = collect(File::allFiles($plugin))
        ->map(fn (SplFileInfo $file): string => str_replace(
            DIRECTORY_SEPARATOR,
            '/',
            Str::after($file->getPathname(), $plugin.DIRECTORY_SEPARATOR),
        ))
        ->sort()
        ->values()
        ->all();

    expect($exitCode)->toBe(0)
        ->and($files)->toBe([
            'LICENSE',
            'README.md',
            'composer.json',
            'frontend/eslint.config.js',
            'frontend/package.json',
            'frontend/src/pages/index.tsx',
            'frontend/src/plugin.css',
            'frontend/src/plugin.ts',
            'frontend/src/support.ts',
            'frontend/tsconfig.json',
            'frontend/vite.config.ts',
            'lang/en/admin.php',
            'lang/zh_CN/admin.php',
            'plugin.php',
            'routes/admin.php',
            'src/Http/Controllers/TicketsController.php',
            'src/TicketsPluginHook.php',
        ])
        ->and($composer['name'])->toBe('acme/tickets')
        ->and($composer['type'])->toBe('zeno-plugin')
        ->and($composer['autoload']['psr-4']['Acme\\Tickets\\'])->toBe('src/')
        ->and(File::exists($plugin.'/src/Http/Controllers/TicketsController.php'))->toBeTrue()
        ->and(File::get($plugin.'/src/Http/Controllers/TicketsController.php'))
        ->toContain('namespace Acme\\Tickets\\Http\\Controllers;')
        ->and(File::exists($plugin.'/src/TicketsPluginHook.php'))->toBeTrue()
        ->and(File::get($plugin.'/plugin.php'))
        ->toContain('TicketsPluginHook::class', "routeName: 'tickets.index'")
        ->and(File::get($plugin.'/frontend/src/plugin.ts'))->toContain("key: 'tickets'")
        ->and(File::isDirectory($plugin.'/tests'))->toBeFalse()
        ->and(Artisan::all()['zeno:plugin:make']->getDefinition()->hasOption('path'))->toBeFalse()
        ->and(Artisan::all()['zeno:plugin:make']->getDefinition()->hasOption('backend-only'))->toBeTrue()
        ->and(Artisan::output())->toContain($plugin.'/README.md')
        ->not->toContain('npm --prefix');
});

it('creates a backend-only plugin without panel UI', function () {
    $exitCode = Artisan::call('zeno:plugin:make', [
        'package' => 'acme/tickets',
        '--backend-only' => true,
    ]);

    $plugin = $this->pluginMakeBasePath.'/packages/tickets';
    $composer = json_decode(File::get($plugin.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $files = collect(File::allFiles($plugin))
        ->map(fn (SplFileInfo $file): string => str_replace(
            DIRECTORY_SEPARATOR,
            '/',
            Str::after($file->getPathname(), $plugin.DIRECTORY_SEPARATOR),
        ))
        ->sort()
        ->values()
        ->all();
    $manifest = File::get($plugin.'/plugin.php');

    expect($exitCode)->toBe(0)
        ->and($files)->toBe([
            'LICENSE',
            'README.md',
            'composer.json',
            'lang/en/admin.php',
            'lang/zh_CN/admin.php',
            'plugin.php',
            'src/TicketsPluginHook.php',
        ])
        ->and($composer['name'])->toBe('acme/tickets')
        ->and($composer['require'])->not->toHaveKey('inertiajs/inertia-laravel')
        ->and($manifest)->toContain("'menus' => []")
        ->not->toContain('MenuDefinition')
        ->and(require $plugin.'/lang/en/admin.php')->toBe([])
        ->and(require $plugin.'/lang/zh_CN/admin.php')->toBe([])
        ->and(File::get($plugin.'/README.md'))->toContain('纯后端 Zeno Admin 插件')
        ->not->toContain('npm run');
});

it('prompts for a missing package name', function () {
    $this->artisan('zeno:plugin:make')
        ->expectsQuestion('Composer package name', 'acme/help-desk')
        ->assertSuccessful();

    expect(File::exists($this->pluginMakeBasePath.'/packages/help-desk/src/HelpDeskPluginHook.php'))->toBeTrue();
});

it('fails when the target directory already exists', function () {
    $target = $this->pluginMakeBasePath.'/packages/tickets';
    File::makeDirectory($target, recursive: true);
    File::put($target.'/keep.txt', 'keep');

    $this->artisan('zeno:plugin:make', [
        'package' => 'acme/tickets',
    ])->assertFailed();

    expect(File::get($target.'/keep.txt'))->toBe('keep');
});

it('requires a package name in non-interactive mode', function () {
    $this->artisan('zeno:plugin:make', [
        '--no-interaction' => true,
    ])->assertFailed();

    expect(File::exists($this->pluginMakeBasePath.'/packages'))->toBeFalse();
});
