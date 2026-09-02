<?php

use Zeno\Plugin\Definitions\MenuDefinition;

return [
    'name' => 'Invalid link menu key',
    'menus' => [
        new MenuDefinition(
            titleKey: 'plugins.invalid-link-menu-key.menu_titles.groups.index',
            url: 'https://example.com/plugin',
        ),
    ],
];
