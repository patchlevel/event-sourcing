<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface Projection
{
    /** @return iterable<class-string<AggregateChanged<array<string, mixed>>>, string> */
    public function handledEvents(): iterable;

    public function create(): void;

    public function drop(): void;
}
