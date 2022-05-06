<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Upcast;

/**
 * @psalm-immutable
 */
final class Upcast
{
    /**
     * @param class-string         $class
     * @param array<string, mixed> $payload
     */
    public function __construct(public string $class, public array $payload)
    {
    }
}
