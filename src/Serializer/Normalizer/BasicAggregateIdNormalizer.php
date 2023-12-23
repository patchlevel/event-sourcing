<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Normalizer;

use Attribute;
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRootId;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class BasicAggregateIdNormalizer extends AggregateIdNormalizer
{
    public function __construct()
    {
        parent::__construct(BasicAggregateRootId::class);
    }
}
