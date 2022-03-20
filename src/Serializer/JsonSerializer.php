<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use JsonException;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class JsonSerializer implements Serializer
{
    private Hydrator $hydrator;

    public function __construct(?Hydrator $hydrator = null)
    {
        $this->hydrator = $hydrator ?? new DefaultHydrator();
    }

    public function serialize(object $event): string
    {
        $data = $this->hydrator->extract($event);

        try {
            return json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SerializationNotPossible($event, $e);
        }
    }

    /**
     * @param class-string<T> $class
     *
     * @return T
     *
     * @template T of object
     */
    public function deserialize(string $class, string $data): object
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DeserializationNotPossible($class, $data, $e);
        }

        return $this->hydrator->hydrate($class, $payload);
    }
}
