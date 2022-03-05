<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface ProjectionHandler
{
    /**
     * @param AggregateChanged<array<string, mixed>> $event
     * @param non-empty-array<Projection>|null       $onlyProjections
     */
    public function handle(AggregateChanged $event, ?array $onlyProjections = null): void;

    /**
     * @param non-empty-array<Projection>|null $onlyProjections
     */
    public function create(?array $onlyProjections = null): void;

    /**
     * @param non-empty-array<Projection>|null $onlyProjections
     */
    public function drop(?array $onlyProjections = null): void;
}
