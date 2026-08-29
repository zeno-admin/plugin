<?php

namespace Zeno\Plugin\Tests\Fixtures;

use Zeno\Plugin\PluginHook;

final class RecordingHook extends PluginHook
{
    public function __construct(private readonly HookRecorder $recorder) {}

    public function install(): void
    {
        $this->recorder->record('install');
    }

    public function enable(): void
    {
        $this->recorder->record('enable');
    }

    public function disable(): void
    {
        $this->recorder->record('disable');
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        $this->recorder->record("upgrade:{$fromVersion}:{$toVersion}");
    }

    public function uninstall(): void
    {
        $this->recorder->record('uninstall');
    }
}
