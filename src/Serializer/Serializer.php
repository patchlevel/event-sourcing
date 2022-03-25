<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

interface Serializer
{
    /**
     * @param object $event
     * @return array{name: string, payload: string}
     */
    public function serialize(object $event): array;

    /**
     * @param class-string<T> $class
     *
     * @return T
     *
     * @template T of object
     */
    public function deserialize(string $eventName, string $data): object;
}
