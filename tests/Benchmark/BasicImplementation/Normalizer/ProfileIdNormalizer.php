<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Normalizer;

use Attribute;
use Patchlevel\EventSourcing\Serializer\Normalizer\AggregateIdNormalizer;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ProfileIdNormalizer extends AggregateIdNormalizer
{
    public function __construct()
    {
        parent::__construct(ProfileId::class);
    }
}
