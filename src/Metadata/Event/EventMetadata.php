<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

final class EventMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly bool $splitStream = false,
        /** @var list<string> */
        public readonly array $aliases = [],
    ) {
    }
}
