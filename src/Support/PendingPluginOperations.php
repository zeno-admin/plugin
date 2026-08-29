<?php

namespace Zeno\Plugin\Support;

use Closure;
use JsonException;
use Zeno\Plugin\Exceptions\PluginOperationException;

final class PendingPluginOperations
{
    public function __construct(private ?string $path = null) {}

    /** @return array<array-key, mixed> */
    public function read(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return [];
        }

        return $this->locked($path, 'rb', LOCK_SH, fn ($handle): array => $this->decode($handle));
    }

    /** @param array<string, mixed> $operation */
    public function put(array $operation): void
    {
        $package = $operation['package'] ?? null;

        if (! is_string($package) || $package === '') {
            throw new PluginOperationException('Pending plugin operation requires a package.');
        }

        $this->mutate(fn (array $operations): array => array_replace($operations, [$package => $operation]));
    }

    /** @param array<string, mixed> $expected */
    public function forget(string $package, array $expected): void
    {
        $this->mutate(fn (array $operations): array => ($operations[$package] ?? null) === $expected
            ? array_diff_key($operations, [$package => true])
            : $operations);
    }

    private function path(): string
    {
        return $this->path ?? base_path('plugins'.DIRECTORY_SEPARATOR.'.zeno-pending.json');
    }

    /** @param Closure(array<array-key, mixed>): array<array-key, mixed> $change */
    private function mutate(Closure $change): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new PluginOperationException("Unable to create pending operation directory [{$directory}].");
        }

        $this->locked($path, 'c+b', LOCK_EX, function ($handle) use ($change, $path): void {
            $contents = json_encode(
                $change($this->decode($handle)),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ).PHP_EOL;

            if (
                ! rewind($handle)
                || ! ftruncate($handle, 0)
                || fwrite($handle, $contents) !== strlen($contents)
                || ! fflush($handle)
            ) {
                throw new PluginOperationException("Unable to write pending plugin operations [{$path}].");
            }
        });
    }

    /**
     * @param  resource  $handle
     * @return array<array-key, mixed>
     */
    private function decode($handle): array
    {
        if (! rewind($handle) || ($contents = stream_get_contents($handle)) === false) {
            throw new PluginOperationException("Unable to read pending plugin operations [{$this->path()}].");
        }

        if ($contents === '') {
            return [];
        }

        try {
            $operations = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PluginOperationException('Pending plugin operations are invalid.', previous: $exception);
        }

        return is_array($operations) ? $operations : [];
    }

    /**
     * @template T
     *
     * @param  Closure(resource): T  $callback
     * @param  int<0, 7>  $lock
     * @return T
     */
    private function locked(string $path, string $mode, int $lock, Closure $callback): mixed
    {
        $handle = fopen($path, $mode);

        if ($handle === false || ! flock($handle, $lock)) {
            is_resource($handle) && fclose($handle);

            throw new PluginOperationException("Unable to access pending plugin operations [{$path}].");
        }

        try {
            return $callback($handle);
        } catch (JsonException $exception) {
            throw new PluginOperationException('Unable to encode pending plugin operations.', previous: $exception);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
