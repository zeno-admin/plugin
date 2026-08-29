<?php

namespace Zeno\Plugin\Support;

use Zeno\Plugin\Definitions\PluginDefinition;
use Zeno\Plugin\Exceptions\InvalidPluginException;
use Zeno\Plugin\Exceptions\PluginNotFoundException;

final class PluginRegistry
{
    /** @var array<string, PluginDefinition> */
    private array $plugins = [];

    public function register(PluginDefinition $plugin): void
    {
        if (isset($this->plugins[$plugin->key])) {
            throw new InvalidPluginException("Plugin key [{$plugin->key}] is already registered.");
        }

        $this->plugins[$plugin->key] = $plugin;
    }

    public function has(string $key): bool
    {
        return isset($this->plugins[$key]);
    }

    public function get(string $key): PluginDefinition
    {
        return $this->plugins[$key] ?? throw new PluginNotFoundException($key);
    }

    /** @return array<string, PluginDefinition> */
    public function all(): array
    {
        return $this->plugins;
    }
}
