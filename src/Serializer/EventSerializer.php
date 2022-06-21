<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

interface EventSerializer
{
    /**
     * @param array<string, mixed> $options
     */
    public function serialize(object $event, array $options = []): SerializedEvent;

    /**
     * @param array<string, mixed> $options
     */
    public function deserialize(SerializedEvent $data, array $options = []): object;
}
