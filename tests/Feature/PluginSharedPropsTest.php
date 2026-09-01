<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Tests\TestCase;
use Zeno\Plugin\Enums\PluginStatus;
use Zeno\Plugin\Models\AdminPlugin;

uses(TestCase::class, RefreshDatabase::class);

/** 构造并绑定指定命名路由的请求。 */
function pluginSharedPropsRequest(string $name): Request
{
    $route = Route::getRoutes()->getByName($name);
    $request = Request::create('/'.$route->uri(), 'GET');
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);

    return $request;
}

it('shares enabled plugin keys only for admin routes', function () {
    Route::get('/plugin-shared-admin', fn (): null => null)->name('admin.plugin-shared');
    Route::get('/plugin-shared-public', fn (): null => null)->name('plugin-shared-public');
    Route::getRoutes()->refreshNameLookups();
    AdminPlugin::create([
        'key' => 'enabled-plugin',
        'version' => '1.0.0',
        'status' => PluginStatus::Enabled,
    ]);
    AdminPlugin::create([
        'key' => 'disabled-plugin',
        'version' => '1.0.0',
        'status' => PluginStatus::Disabled,
    ]);
    $resolve = Inertia::getShared('enabledPlugins');

    pluginSharedPropsRequest('admin.plugin-shared');
    expect($resolve())->toBe(['enabled-plugin']);

    pluginSharedPropsRequest('plugin-shared-public');
    expect($resolve())->toBe([]);
});
