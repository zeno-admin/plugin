<?php

namespace Zeno\Plugin\Console\Commands;

use Illuminate\Console\Command;
use Zeno\Plugin\Exceptions\PluginException;
use Zeno\Plugin\Support\PluginManager;

final class PluginEnableCommand extends Command
{
    protected $signature = 'zeno:plugin:enable {plugin}';

    protected $description = 'Enable an installed admin plugin';

    public function handle(PluginManager $manager): int
    {
        try {
            $manager->enable((string) $this->argument('plugin'));
        } catch (PluginException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info('Plugin enabled.');

        return self::SUCCESS;
    }
}
