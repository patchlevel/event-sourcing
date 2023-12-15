<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

final class BasicAggregateRootId implements AggregateRootId
{
    public function __construct(
        private readonly string $id,
    ) {
    }

    public function toString(): string
    {
        return $this->id;
    }
}
