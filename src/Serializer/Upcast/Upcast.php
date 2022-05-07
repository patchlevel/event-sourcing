<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Upcast;

/**
 * @psalm-immutable
 */
final class Upcast
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(public string $eventName, public array $payload)
    {
    }
}
