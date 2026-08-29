<?php

namespace Zeno\Plugin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Zeno\Plugin\Exceptions\PluginNotSyncedException;
use Zeno\Plugin\Support\PluginManager;

final class PluginUninstallInternalCommand extends Command
{
    protected $signature = 'zeno-internal:plugin-uninstall {plugin}';

    protected $description = 'Run a plugin uninstall hook before Composer removes its files';

    public function __construct()
    {
        parent::__construct();
        $this->setHidden(true);
    }

    public function handle(PluginManager $manager): int
    {
        try {
            $manager->uninstall($this->argument('plugin'));
        } catch (PluginNotSyncedException) {
            return self::SUCCESS;
        }

        $this->components->info('Plugin uninstall hook completed.');

        return self::SUCCESS;
    }
}
