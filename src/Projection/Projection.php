<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface Projection
{
    /** @return iterable<class-string<AggregateChanged>, string> */
    public function handledEvents(): iterable;

    public function drop(): void;
}
