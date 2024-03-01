<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Serializer;

interface HeadersSerializer
{
    /**
     * @param list<object>         $headers
     * @param array<string, mixed> $options
     *
     * @return array<SerializedHeader>
     */
    public function serialize(array $headers, array $options = []): array;

    /**
     * @param array<SerializedHeader> $serializedHeaders
     *
     * @return list<object>
     */
    public function deserialize(array $serializedHeaders): array;
}
