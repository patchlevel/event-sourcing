<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;

interface AggregateRootMetadataAware
{
    public static function metadata(): AggregateRootMetadata;

    public static function setMetadataFactory(AggregateRootMetadataFactory $metadataFactory): void;
}
