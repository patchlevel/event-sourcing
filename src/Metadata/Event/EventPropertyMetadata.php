<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Patchlevel\EventSourcing\Serializer\Hydrator\Normalizer;
use ReflectionProperty;

/**
 * @readonly
 */
final class EventPropertyMetadata
{
    public function __construct(
        public string $fieldName,
        public ReflectionProperty $reflection,
        public ?Normalizer $normalizer = null
    ) {
    }
}
