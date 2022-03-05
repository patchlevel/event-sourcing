<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface ProjectionHandler
{
    /**
     * @param AggregateChanged<array<string, mixed>> $event
     * @param list<Projection>|null                  $onlyProjections
     */
    public function handle(AggregateChanged $event, ?array $onlyProjections = null): void;

    /**
     * @param list<Projection>|null $onlyProjections
     */
    public function create(?array $onlyProjections = null): void;

    /**
     * @param list<Projection>|null $onlyProjections
     */
    public function drop(?array $onlyProjections = null): void;
}
