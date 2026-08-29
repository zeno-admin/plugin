<?php

namespace Zeno\Plugin\Definitions;

use Zeno\Plugin\Exceptions\InvalidPluginException;

final readonly class MenuDefinition
{
    /** @param list<MenuDefinition> $children */
    public function __construct(
        public string $titleKey,
        public ?string $routeName = null,
        public ?string $url = null,
        public ?string $icon = null,
        public int $sort = 0,
        public array $children = [],
    ) {
        if ($routeName !== null && $url !== null) {
            throw new InvalidPluginException('Plugin menu links must define either a route name or URL.');
        }

        if (($routeName !== null || $url !== null) && $children !== []) {
            throw new InvalidPluginException('Plugin menu links cannot contain children.');
        }
    }
}
