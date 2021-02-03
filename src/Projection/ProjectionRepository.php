<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface ProjectionRepository
{
    public function handle(AggregateChanged $event): void;

    public function create(): void;

    public function drop(): void;
}
