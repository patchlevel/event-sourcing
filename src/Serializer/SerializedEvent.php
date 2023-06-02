<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

final class SerializedEvent
{
    public function __construct(
        public readonly string $name,
        public readonly string $payload,
    ) {
    }
}
