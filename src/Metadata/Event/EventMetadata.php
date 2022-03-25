<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

/**
 * @internal
 */
final class EventMetadata
{
    public string $name;

    /**
     * @var array<string, EventPropertyMetadata>
     */
    public array $properties = [];
}
