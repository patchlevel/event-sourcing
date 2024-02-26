<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Serializer;

use Patchlevel\EventSourcing\EventBus\Header;

interface HeadersSerializer
{
    /**
     * @param array<string, Header> $headers
     * @return array<SerializedHeader>
     */
    public function serialize(array $headers): array;

    /**
     * @param array<SerializedHeader> $serializedHeaders
     * @return array<string, Header>
     */
    public function deserialize(array $serializedHeaders): array;
}
