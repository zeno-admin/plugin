<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->pluginMakePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zeno-plugin-make-'.Str::random(12);
    File::makeDirectory($this->pluginMakePath);
});

afterEach(function () {
    File::deleteDirectory($this->pluginMakePath);
});

it('creates a plugin from the fixed stubs', function () {
    $exitCode = Artisan::call('zeno:plugin:make', [
        'package' => 'acme/tickets',
        '--path' => $this->pluginMakePath,
    ]);

    $plugin = $this->pluginMakePath.DIRECTORY_SEPARATOR.'tickets';
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
        ->and(File::isDirectory($plugin.'/tests'))->toBeFalse();
});

it('prompts for a missing package name', function () {
    $this->artisan('zeno:plugin:make', ['--path' => $this->pluginMakePath])
        ->expectsQuestion('Composer package name', 'acme/help-desk')
        ->assertSuccessful();

    expect(File::exists($this->pluginMakePath.'/help-desk/src/HelpDeskPluginHook.php'))->toBeTrue();
});

it('fails when the target directory already exists', function () {
    $target = $this->pluginMakePath.'/tickets';
    File::makeDirectory($target);
    File::put($target.'/keep.txt', 'keep');

    $this->artisan('zeno:plugin:make', [
        'package' => 'acme/tickets',
        '--path' => $this->pluginMakePath,
    ])->assertFailed();

    expect(File::get($target.'/keep.txt'))->toBe('keep');
});

it('requires a package name in non-interactive mode', function () {
    $this->artisan('zeno:plugin:make', [
        '--path' => $this->pluginMakePath,
        '--no-interaction' => true,
    ])->assertFailed();

    expect(File::directories($this->pluginMakePath))->toBe([]);
});
