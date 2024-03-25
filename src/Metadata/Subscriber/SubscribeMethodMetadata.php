<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

final class SubscribeMethodMetadata
{
    /** @param list<ArgumentMetadata> $arguments */
    public function __construct(
        public readonly string $name,
        public readonly array $arguments = [],
    ) {
    }
}
