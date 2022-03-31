<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Patchlevel\EventSourcing\Serializer\Normalizer;
use ReflectionProperty;

/**
 * @readonly
 */
class EventPropertyMetadata
{
    public function __construct(
        public string $fieldName,
        public ReflectionProperty $reflection,
        public ?Normalizer $normalizer = null
    ) {
    }
}
