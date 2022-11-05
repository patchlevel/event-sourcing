<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

final class EventMetadata
{
    public function __construct(
        public readonly string $name,
        /** @var array<string, EventPropertyMetadata> */
        public readonly array $properties = [],
        public readonly bool $splitStream = false,
    ) {
    }
}
