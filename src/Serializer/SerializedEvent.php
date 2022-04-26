<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

/**
 * @readonly
 */
final class SerializedEvent
{
    public function __construct(
        public string $name,
        public string $payload
    ) {
    }
}
