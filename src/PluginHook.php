<?php

namespace Zeno\Plugin;

abstract class PluginHook
{
    public function install(): void {}

    public function enable(): void {}

    public function disable(): void {}

    public function upgrade(string $fromVersion, string $toVersion): void {}

    public function uninstall(): void {}
}
