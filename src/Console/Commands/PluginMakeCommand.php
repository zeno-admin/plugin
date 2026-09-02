<?php

namespace Zeno\Plugin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;

final class PluginMakeCommand extends Command
{
    protected $signature = 'zeno:plugin:make
                            {package? : Composer package name, e.g. acme/tickets}
                            {--backend-only : Generate a plugin without frontend or panel UI}
                            {--no-menu : Generate a full plugin without navigation menu}';

    protected $description = 'Create a Zeno plugin from the default stubs';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $package = $this->packageName();

        if ($package === null) {
            return self::FAILURE;
        }

        [$vendor, $key] = explode('/', $package, 2);
        $target = base_path("packages/{$key}");

        if ($this->files->exists($target) || is_link($target)) {
            error("Plugin directory [{$target}] already exists.");

            return self::FAILURE;
        }

        $stubs = dirname(__DIR__, 3).'/stubs/plugin';

        if (! $this->files->isDirectory($stubs)) {
            error("Plugin stubs directory [{$stubs}] does not exist.");

            return self::FAILURE;
        }

        $class = Str::studly($key);
        $namespace = Str::studly($vendor).'\\'.$class;
        $backendOnly = (bool) $this->option('backend-only');
        $noMenu = (bool) $this->option('no-menu');
        $replacements = [
            '{{ package }}' => $package,
            '{{ key }}' => $key,
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $class,
            '{{ title }}' => Str::headline($key),
            '{{ year }}' => now()->format('Y'),
            '{{ vendor }}' => $vendor,
        ];

        try {
            $templates = [];

            foreach ($this->files->allFiles($stubs) as $stub) {
                $stubPath = $stub->getRelativePathname();

                if (str_starts_with($stubPath, 'variants/')) {
                    continue;
                }

                if ($backendOnly && $this->skipForBackendOnly($stubPath)) {
                    continue;
                }

                if (! $backendOnly && $noMenu && $this->replaceForNoMenu($stubPath)) {
                    continue;
                }

                $templates[] = [$stub, $stubPath];
            }

            if ($backendOnly) {
                foreach ($this->files->allFiles($stubs.'/variants/backend') as $stub) {
                    $templates[] = [$stub, $stub->getRelativePathname()];
                }
            } elseif ($noMenu) {
                foreach ($this->files->allFiles($stubs.'/variants/no-menu') as $stub) {
                    $templates[] = [$stub, $stub->getRelativePathname()];
                }
            }

            foreach ($templates as [$stub, $stubPath]) {
                $relativePath = $this->outputPath($stubPath, $class);
                $destination = $target.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                $contents = $stub->getContents();
                $fileReplacements = $replacements;

                if ($stubPath === 'composer.json.stub') {
                    $contents = str_replace('{{ namespace }}', str_replace('\\', '\\\\', $namespace), $contents);
                    unset($fileReplacements['{{ namespace }}']);
                }

                $this->files->ensureDirectoryExists(dirname($destination));
                $written = $this->files->put(
                    $destination,
                    str_replace(array_keys($fileReplacements), array_values($fileReplacements), $contents),
                );

                if ($written === false) {
                    throw new RuntimeException("Unable to write plugin file [{$destination}].");
                }
            }
        } catch (Throwable $exception) {
            $this->files->deleteDirectory($target);
            error($exception->getMessage());

            return self::FAILURE;
        }

        info("Plugin [{$package}] created successfully.");
        note("See [{$target}/README.md] for next steps.");

        return self::SUCCESS;
    }

    private function packageName(): ?string
    {
        $package = $this->argument('package');

        if ($package === null && ! $this->input->isInteractive()) {
            error('The package argument is required in non-interactive mode.');

            return null;
        }

        $package = $package === null
            ? text(
                label: 'Composer package name',
                placeholder: 'acme/tickets',
                required: true,
                validate: fn (string $value): ?string => $this->packageError($value),
            )
            : (string) $package;

        if ($message = $this->packageError($package)) {
            error($message);

            return null;
        }

        return $package;
    }

    private function packageError(string $package): ?string
    {
        return preg_match('/^[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?\/[a-z][a-z0-9-]*$/', $package) === 1
            ? null
            : 'Use a lowercase Composer package name such as acme/tickets.';
    }

    private function outputPath(string $stubPath, string $class): string
    {
        return match ($stubPath) {
            'src/Http/Controllers/PluginController.php.stub' => "src/Http/Controllers/{$class}Controller.php",
            'src/PluginHook.php.stub' => "src/{$class}PluginHook.php",
            default => Str::beforeLast($stubPath, '.stub'),
        };
    }

    private function skipForBackendOnly(string $stubPath): bool
    {
        return str_starts_with($stubPath, 'frontend/')
            || in_array($stubPath, [
                'README.md.stub',
                'composer.json.stub',
                'lang/en/admin.php.stub',
                'lang/zh_CN/admin.php.stub',
                'plugin.php.stub',
                'routes/admin.php.stub',
                'src/Http/Controllers/PluginController.php.stub',
            ], true);
    }

    private function replaceForNoMenu(string $stubPath): bool
    {
        return in_array($stubPath, [
            'plugin.php.stub',
            'lang/en/admin.php.stub',
            'lang/zh_CN/admin.php.stub',
        ], true);
    }
}
