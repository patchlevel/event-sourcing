<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

interface Serializer
{
    public const OPTION_PRETTY_PRINT = 'pretty_print';

    /**
     * @param array<string, mixed> $options
     */
    public function serialize(object $event, array $options = []): SerializedData;

    /**
     * @param array<string, mixed> $options
     */
    public function deserialize(SerializedData $data, array $options = []): object;
}
