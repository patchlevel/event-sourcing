<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Serializer;

use Patchlevel\EventSourcing\EventBus\Header;

interface HeadersSerializer
{
    /**
     * @param list<Header> $headers
     * @param array<string, mixed> $options
     *
     * @return array<SerializedHeader>
     */
    public function serialize(array $headers, array $options = []): array;

    /**
     * @param array<SerializedHeader> $serializedHeaders
     *
     * @return list<Header>
     */
    public function deserialize(array $serializedHeaders): array;
}
