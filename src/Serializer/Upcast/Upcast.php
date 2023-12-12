<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Upcast;

/** @psalm-immutable */
final class Upcast
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly string $eventName,
        public readonly array $payload,
    ) {
    }

    public function replaceEventName(string $eventName): self
    {
        return new self($eventName, $this->payload);
    }

    /** @param array<string, mixed> $payload */
    public function replacePayload(array $payload): self
    {
        return new self($this->eventName, $payload);
    }

    public function replacePayloadByKey(string $key, mixed $data): self
    {
        $payload = $this->payload;
        $payload[$key] = $data;

        return new self($this->eventName, $payload);
    }
}
