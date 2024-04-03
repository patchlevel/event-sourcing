<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Serializer;

interface HeadersSerializer
{
    /**
     * @param list<object>         $headers
     * @param array<string, mixed> $options
     */
    public function serialize(array $headers, array $options = []): string;

    /**
     * @param array<string, mixed> $options
     *
     * @return list<object>
     */
    public function deserialize(string $string, array $options = []): array;
}
