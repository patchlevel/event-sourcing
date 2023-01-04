<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootMetadataFactory;

trait AggregateRootMetadataAwareBehaviour
{
    private static ?AggregateRootMetadataFactory $metadataFactory = null;

    public static function metadata(): AggregateRootMetadata
    {
        if (static::class === self::class) {
            throw new MetadataNotPossible();
        }

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
