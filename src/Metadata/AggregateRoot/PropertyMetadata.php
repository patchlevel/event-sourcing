<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

final class PropertyMetadata
{
    public function __construct(
        public readonly string $propertyName,
        public readonly string $fieldName,
        public readonly bool $isPersonalData = false,
        public readonly mixed $personalDataFallback = null,
    ) {
    }
}
