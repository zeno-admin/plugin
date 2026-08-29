<?php

namespace Zeno\Plugin\Console\Commands;

use Illuminate\Console\Command;
use Zeno\Plugin\Exceptions\PluginException;
use Zeno\Plugin\Support\PluginManager;

final class PluginDisableCommand extends Command
{
    protected $signature = 'zeno:plugin:disable {plugin}';

    protected $description = 'Disable an installed admin plugin';

    public function handle(PluginManager $manager): int
    {
        try {
            $manager->disable((string) $this->argument('plugin'));
        } catch (PluginException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info('Plugin disabled.');

        return self::SUCCESS;
    }
}
