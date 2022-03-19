<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events;

final class NameChanged
{
    public function __construct(
        public string $name
    ) {
    }
}
