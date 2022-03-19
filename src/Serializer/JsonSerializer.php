<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use JsonException;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class JsonSerializer implements Serializer
{
    public function serialize(AggregateChanged $event): string
    {
        try {
            return json_encode($event->payload(), JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SerializationNotPossible($event, $e);
        }
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
        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DeserializationNotPossible($class, $data, $e);
        }

        return new $class($payload);
    }
}
