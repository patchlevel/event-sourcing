<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

class JsonSerializer implements Serializer
{
    public function serialize(AggregateChanged $event): string
    {
        return json_encode($event->payload(), JSON_THROW_ON_ERROR);
    }

    /**
     * @param class-string<T> $class
     *
     * @return T
     *
     * @template T of AggregateChanged
     */
    public function deserialize(string $class, string $data): AggregateChanged
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

        return new $class($payload);
    }
}
