<?php

namespace Zeno\Plugin\Tests\Fixtures;

use RuntimeException;

final class HookRecorder
{
    /** @var list<string> */
    public array $events = [];

    public ?string $failOn = null;

    public function record(string $event): void
    {
        $this->events[] = $event;

        if ($this->failOn === $event) {
            throw new RuntimeException("{$event} failed");
        }
    }
}
