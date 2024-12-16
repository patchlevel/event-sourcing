<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Header;

/**
 * @psalm-immutable
 * @experimental
 */
final class StreamNameHeader
{
    public function __construct(
        public readonly string $streamName,
    ) {
    }
}
