<?php

return [
    'route' => [
        'prefix_config' => 'admin.route_prefix',
        'name_prefix_config' => 'admin.route_prefix',
        'middleware' => [
            'before_plugin' => ['web', 'auth:admin'],
            'after_plugin' => [],
            'authenticated' => [],
            'authorized' => ['permission.check'],
        ],
    ],
];
