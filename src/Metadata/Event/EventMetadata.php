<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

/**
 * @readonly
 */
final class EventMetadata
{
    public function __construct(
        public string $name,
        /** @var array<string, EventPropertyMetadata> */
        public array $properties = []
    ) {
    }
}
