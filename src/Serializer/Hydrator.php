<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

interface Hydrator
{
    /**
     * @param class-string<T>      $class
     * @param array<string, mixed> $data
     *
     * @return T
     *
     * @template T of object
     */
    public function hydrate(string $class, array $data): object;

    /**
     * @return array<string, mixed>
     */
    public function extract(object $object): array;
}
