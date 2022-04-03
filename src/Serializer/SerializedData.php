<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

/**
 * @readonly
 */
class SerializedData
{
    public function __construct(
        public string $name,
        public string $payload
    ) {
    }
}
