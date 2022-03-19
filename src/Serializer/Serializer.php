<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface Serializer
{
    public function serialize(AggregateChanged $event): string;

    /**
     * @param class-string<T> $class
     *
     * @return T
     *
     * @template T of AggregateChanged
     */
    public function deserialize(string $class, string $data): AggregateChanged;
}
