<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Patchlevel\EventSourcing\Serializer\Normalizer\Normalizer;
use ReflectionProperty;

final class EventPropertyMetadata
{
    public function __construct(
        public readonly string $fieldName,
        public readonly ReflectionProperty $reflection,
        public readonly Normalizer|null $normalizer = null,
    ) {
    }
}
