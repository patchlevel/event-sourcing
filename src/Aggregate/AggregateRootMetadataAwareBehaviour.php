<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootMetadataFactory;
use Patchlevel\Hydrator\Attribute\Ignore;

trait AggregateRootMetadataAwareBehaviour
{
    #[Ignore]
    private static AggregateRootMetadataFactory|null $metadataFactory = null;

    public static function metadata(): AggregateRootMetadata
    {
        if (!self::$metadataFactory) {
            self::$metadataFactory = new AttributeAggregateRootMetadataFactory();
        }

        return self::$metadataFactory->metadata(static::class);
    }

    public static function setMetadataFactory(AggregateRootMetadataFactory $metadataFactory): void
    {
        self::$metadataFactory = $metadataFactory;
    }
}
