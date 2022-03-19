<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

interface Serializer
{
    public function serialize(object $event): string;

    /**
     * @param class-string<T> $class
     *
     * @return T
     *
     * @template T of object
     */
    public function deserialize(string $class, string $data): object;
}
