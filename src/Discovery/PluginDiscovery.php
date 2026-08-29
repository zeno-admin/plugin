<?php

namespace Zeno\Plugin\Discovery;

use Composer\InstalledVersions;
use Zeno\Plugin\Definitions\PluginDefinition;
use Zeno\Plugin\Exceptions\InvalidPluginException;

final readonly class PluginDiscovery
{
    public function __construct(private PluginManifestLoader $manifests) {}

    /** @return list<PluginDefinition> */
    public function discover(): array
    {
        $plugins = [];
        $packages = [];

        foreach (InstalledVersions::getInstalledPackagesByType('zeno-plugin') as $package) {
            $directory = $this->directory($package);

            if (isset($packages[$directory->key]) && $packages[$directory->key] !== $package) {
                throw new InvalidPluginException(
                    "Composer packages [{$packages[$directory->key]}] and [{$package}] share plugin key [{$directory->key}].",
                );
            }

            if (isset($packages[$directory->key])) {
                continue;
            }

            $packages[$directory->key] = $package;
            $plugins[] = $this->manifests->load($directory);
        }

        usort($plugins, fn (PluginDefinition $left, PluginDefinition $right): int => $left->key <=> $right->key);

        return $plugins;
    }

    private function directory(string $package): PluginDirectory
    {
        $installed = InstalledVersions::getInstallPath($package);

        if ($installed === null) {
            throw new InvalidPluginException("Composer package [{$package}] has no install path.");
        }

        $parts = explode('/', $package);
        $key = count($parts) === 2 ? $parts[1] : '';

        if (preg_match('/^[a-z][a-z0-9-]*$/', $key) !== 1) {
            throw new InvalidPluginException("Plugin package name [{$package}] must match [vendor/plugin-key].");
        }

        $directory = new PluginDirectory($key, $package, $installed);

        if (! is_file($directory->manifestPath())) {
            throw new InvalidPluginException("Plugin [{$package}] is missing required file [{$directory->manifestPath()}].");
        }

        return $directory;
    }
}
