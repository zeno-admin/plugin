<?php

namespace Zeno\Plugin\Exceptions;

final class PluginNotSyncedException extends PluginException
{
    public function __construct(public readonly string $plugin)
    {
        parent::__construct("Plugin [{$plugin}] is not synced.");
    }
}
