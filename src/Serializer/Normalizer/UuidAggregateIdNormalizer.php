<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Normalizer;

use Attribute;
use Patchlevel\EventSourcing\Aggregate\UuidAggregateRootId;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class UuidAggregateIdNormalizer extends AggregateIdNormalizer
{
    public function __construct()
    {
        parent::__construct(UuidAggregateRootId::class);
    }
}
