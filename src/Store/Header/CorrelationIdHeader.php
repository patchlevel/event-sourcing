<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Header;

/** @immutable */
final class CorrelationIdHeader
{
    public function __construct(
        public readonly string $correlationId,
    ) {
    }
}
