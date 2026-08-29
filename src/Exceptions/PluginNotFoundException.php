<?php

namespace Zeno\Plugin\Exceptions;

final class PluginNotFoundException extends PluginException
{
    public function __construct(public readonly string $plugin)
    {
        parent::__construct("Plugin [{$plugin}] is not registered.");
    }
}
