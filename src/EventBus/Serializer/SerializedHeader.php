<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Serializer;

final class SerializedHeader
{
    public function __construct(
        public readonly string $name,
        public readonly string $payload,
    ) {
    }
}
