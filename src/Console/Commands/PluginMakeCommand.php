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
                            {--path=packages : Base directory for generated plugins}';

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
        $target = $this->targetPath((string) $this->option('path'), $key);

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
            foreach ($this->files->allFiles($stubs) as $stub) {
                $relativePath = $this->outputPath($stub->getRelativePathname(), $class);
                $destination = $target.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                $contents = $stub->getContents();
                $fileReplacements = $replacements;

                if ($stub->getRelativePathname() === 'composer.json.stub') {
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
        note($this->nextSteps($package, $target, $key));

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

    private function targetPath(string $path, string $key): string
    {
        $isAbsolute = str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

        return ($isAbsolute ? rtrim($path, '/\\') : base_path(trim($path, '/\\')))
            .DIRECTORY_SEPARATOR.$key;
    }

    private function outputPath(string $stubPath, string $class): string
    {
        return match ($stubPath) {
            'src/Http/Controllers/PluginController.php.stub' => "src/Http/Controllers/{$class}Controller.php",
            'src/PluginHook.php.stub' => "src/{$class}PluginHook.php",
            default => Str::beforeLast($stubPath, '.stub'),
        };
    }

    private function nextSteps(string $package, string $target, string $key): string
    {
        $frontend = $target.DIRECTORY_SEPARATOR.'frontend';

        return <<<TEXT
Next steps:

npm --prefix "{$frontend}" install
npm --prefix "{$frontend}" run build
composer config repositories.{$key} path "{$target}"
composer require {$package}:@dev
php artisan migrate
npm run build
TEXT;
    }
}
