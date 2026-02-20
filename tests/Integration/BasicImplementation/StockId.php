<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation;

use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Aggregate\RamseyUuidV7Behaviour;

final readonly class StockId implements AggregateRootId
{
    use RamseyUuidV7Behaviour;

    public static function create(): self
    {
        return self::fromString('9247e40b-bd9b-4592-bb93-28986df07e2b');
    }
}
