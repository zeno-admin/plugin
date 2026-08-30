<?php

use Illuminate\Config\Repository;
use Tests\TestCase;
use Zeno\Plugin\PluginServiceProvider;

uses(TestCase::class);

it('loads default configuration before the host publishes it', function () {
    $items = config()->all();
    unset($items['plugin']);
    app()->instance('config', new Repository($items));

    (new PluginServiceProvider(app()))->register();

    expect(config('plugin.route.prefix_config'))->toBe('admin.route_prefix')
        ->and(config('plugin.route.name_prefix_config'))->toBe('admin.route_prefix')
        ->and(config('plugin.route.middleware.authorized'))->toBe(['permission.check']);
});
