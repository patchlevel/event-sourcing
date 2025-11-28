<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Header;

/** @immutable */
final class PlayheadHeader
{
    /** @param positive-int $playhead */
    public function __construct(
        public readonly int $playhead,
    ) {
    }
}
