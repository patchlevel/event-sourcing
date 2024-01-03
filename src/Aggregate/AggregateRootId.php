<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

interface AggregateRootId
{
    public function toString(): string;

    public static function fromString(string $id): self;
}
