<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use Patchlevel\EventSourcing\Serializer\Normalizer\ArrayNormalizer;
use Patchlevel\EventSourcing\Serializer\Normalizer\Normalizer;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Normalize
{
    private Normalizer $normalizer;

    public function __construct(Normalizer $normalizer, bool $list = false)
    {
        $this->normalizer = $list ? new ArrayNormalizer($normalizer) : $normalizer;
    }

    public function normalizer(): Normalizer
    {
        return $this->normalizer;
    }
}
