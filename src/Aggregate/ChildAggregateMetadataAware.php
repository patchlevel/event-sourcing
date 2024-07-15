<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\EventSourcing\Metadata\ChildAggregate\ChildAggregateMetadata;
use Patchlevel\EventSourcing\Metadata\ChildAggregate\ChildAggregateMetadataFactory;

interface ChildAggregateMetadataAware
{
    public static function metadata(): ChildAggregateMetadata;

    public static function setMetadataFactory(ChildAggregateMetadataFactory $metadataFactory): void;
}
