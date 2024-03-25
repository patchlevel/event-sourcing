<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

final class EventMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly bool $splitStream = false,
        public readonly string|null $dataSubjectIdField = null,
        /** @var array<string, PropertyMetadata> */
        public readonly array $propertyMetadata = [],
    ) {
    }
}
