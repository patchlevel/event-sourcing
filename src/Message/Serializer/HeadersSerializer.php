<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Serializer;

interface HeadersSerializer
{
    /**
     * @param list<object>         $headers
     * @param array<string, mixed> $options
     *
     * @return array<string, string>
     */
    public function serialize(array $headers, array $options = []): array;

    /**
     * @param array<string, string> $serializedHeaders
     *
     * @return list<object>
     */
    public function deserialize(array $serializedHeaders): array;
}
