<?php

namespace Zeno\Plugin\Definitions;

use Zeno\Plugin\Enums\RouteMode;

final readonly class RouteDefinition
{
    public function __construct(
        public string $path,
        public RouteMode $mode = RouteMode::Authorized,
    ) {}
}
