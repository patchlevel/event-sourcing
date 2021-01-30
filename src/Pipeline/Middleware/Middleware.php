<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface Middleware
{
    /**
     * @return list<AggregateChanged>
     */
    public function __invoke(AggregateChanged $aggregateChanged): array;
}
