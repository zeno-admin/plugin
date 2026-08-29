<?php

namespace Zeno\Plugin\Console\Commands;

use Illuminate\Console\Command;
use Zeno\Plugin\Exceptions\PluginException;
use Zeno\Plugin\Support\PluginManager;

final class PluginSyncCommand extends Command
{
    protected $signature = 'zeno:plugin:sync {plugin}';

    protected $description = 'Sync menu definitions for an installed admin plugin';

    public function handle(PluginManager $manager): int
    {
        try {
            $manager->sync((string) $this->argument('plugin'));
        } catch (PluginException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info('Plugin synced.');

        return self::SUCCESS;
    }
}
