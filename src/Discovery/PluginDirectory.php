<?php

namespace Zeno\Plugin\Discovery;

final readonly class PluginDirectory
{
    public function __construct(
        public string $key,
        public string $package,
        public string $path,
    ) {}

    public function manifestPath(): string
    {
        return $this->path.'/plugin.php';
    }

    public function configPath(): string
    {
        return $this->path.'/config/plugin.php';
    }

    public function authorizedRoutesPath(): string
    {
        return $this->path.'/routes/admin.php';
    }

    public function authenticatedRoutesPath(): string
    {
        return $this->path.'/routes/authenticated.php';
    }

    public function migrationPath(): string
    {
        return $this->path.'/database/migrations';
    }

    public function translationPath(): string
    {
        return $this->path.'/lang';
    }

    public function frontendPath(): string
    {
        return $this->path.'/frontend';
    }

    public function frontendEntryPath(): string
    {
        return $this->frontendPath().'/dist/plugin.js';
    }

    public function hasFrontend(): bool
    {
        return is_dir($this->frontendPath());
    }
}
