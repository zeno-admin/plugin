<?php

namespace Zeno\Plugin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Zeno\Plugin\Models\AdminPlugin;
use Zeno\Plugin\Support\PluginRegistry;

final class PluginListCommand extends Command
{
    protected $signature = 'zeno:plugin:list';

    protected $description = 'List discovered admin plugins';

    public function handle(PluginRegistry $plugins): int
    {
        $installed = AdminPlugin::query()->get()->keyBy('key');

        $rows = collect($plugins->all())->map(fn ($plugin, string $key): array => [
            $key,
            $plugin->name,
            $plugin->package,
            $installed->get($key)?->status->value ?? 'discovered',
        ])->values()->all();

        $this->table(['Key', 'Name', 'Package', 'Status'], $rows);

        return self::SUCCESS;
    }
}
