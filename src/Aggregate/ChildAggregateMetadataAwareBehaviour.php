<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\EventSourcing\Metadata\ChildAggregate\AttributeChildAggregateMetadataFactory;
use Patchlevel\EventSourcing\Metadata\ChildAggregate\ChildAggregateMetadata;
use Patchlevel\EventSourcing\Metadata\ChildAggregate\ChildAggregateMetadataFactory;
use Patchlevel\Hydrator\Attribute\Ignore;

trait ChildAggregateMetadataAwareBehaviour
{
    #[Ignore]
    private static ChildAggregateMetadataFactory|null $metadataFactory = null;

    /** @return ChildAggregateMetadata<self> */
    public static function metadata(): ChildAggregateMetadata
    {
        if (!self::$metadataFactory) {
            self::$metadataFactory = new AttributeChildAggregateMetadataFactory();
        }

        return self::$metadataFactory->metadata(static::class);
    }

    public static function setMetadataFactory(ChildAggregateMetadataFactory $metadataFactory): void
    {
        self::$metadataFactory = $metadataFactory;
    }
}
