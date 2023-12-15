<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

trait ValueAggregateIdBehaviour
{
    public function __construct(
        private readonly string $id,
    ) {
    }

    public static function fromString(string $id): self
    {
        return new self($id);
    }

    public function toString(): string
    {
        return $this->id;
    }
}
