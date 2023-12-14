<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Debug;

use function microtime;

final class ProfileData
{
    private float|null $start = null;
    private float|null $duration = null;

    public function __construct(
        private readonly string $name,
        private readonly array  $context = [],
    ) {
    }

    public function start(): void
    {
        $this->start = microtime(true);
    }

    public function stop(): void
    {
        if ($this->start === null) {
            return;
        }

        $this->duration = microtime(true) - $this->start;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function context(): array
    {
        return $this->context;
    }

    public function duration(): float|null
    {
        return $this->duration;
    }
}
