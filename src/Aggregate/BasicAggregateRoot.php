<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;

abstract class BasicAggregateRoot implements AggregateRoot, AggregateRootMetadataAware
{
    use AggregateRootAttributeBehaviour {
        metadata as getMetadata;
    }

    public static function metadata(): AggregateRootMetadata
    {
        if (static::class === self::class) {
            throw new MetadataNotPossible();
        }

        return static::getMetadata();
    }
}
