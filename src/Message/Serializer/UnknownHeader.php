<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Serializer;

interface UnknownHeader
{
    public function name(): string;

    /** @return array<array-key, mixed> */
    public function payload(): array;
}
