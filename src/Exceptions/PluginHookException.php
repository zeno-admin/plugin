<?php

namespace Zeno\Plugin\Exceptions;

use Throwable;

final class PluginHookException extends PluginException
{
    public function __construct(string $plugin, string $hook, Throwable $previous)
    {
        parent::__construct(
            "Plugin [{$plugin}] {$hook} hook failed: {$previous->getMessage()}",
            previous: $previous,
        );
    }
}
