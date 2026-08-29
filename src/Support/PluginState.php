<?php

namespace Zeno\Plugin\Support;

use Zeno\Plugin\Enums\PluginStatus;
use Zeno\Plugin\Models\AdminPlugin;

final class PluginState
{
    public function enabled(string $key): bool
    {
        return AdminPlugin::query()
            ->whereKey($key)
            ->where('status', PluginStatus::Enabled->value)
            ->exists();
    }

    /** @return list<string> */
    public function enabledKeys(): array
    {
        return array_values(
            AdminPlugin::query()
                ->where('status', PluginStatus::Enabled->value)
                ->orderBy('key')
                ->pluck('key')
                ->map(fn (mixed $key): string => (string) $key)
                ->all(),
        );
    }
}
