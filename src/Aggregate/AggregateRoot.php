<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

abstract class AggregateRoot implements AggregateRootInterface, AggregateRootMetadataAware
{
    use AggregateRootAttributeBehaviour;
}
