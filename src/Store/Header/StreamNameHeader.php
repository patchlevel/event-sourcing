<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Header;

use Patchlevel\EventSourcing\Attribute\Header;

/** @immutable */
#[Header('streamName')]
final class StreamNameHeader
{
    public function __construct(
        public readonly string $streamName,
    ) {
    }
}
