<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

/**
 * @readonly
 */
class SerializedEvent
{
    public function __construct(
        public string $name,
        public string $payload
    ) {
    }
}
