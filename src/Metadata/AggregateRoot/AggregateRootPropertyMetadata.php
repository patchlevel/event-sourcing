<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Serializer\Hydrator\Normalizer;
use ReflectionProperty;

final class AggregateRootPropertyMetadata
{
    public function __construct(
        public readonly string $fieldName,
        public readonly ReflectionProperty $reflection,
        public readonly ?Normalizer $normalizer = null
    ) {
    }
}
