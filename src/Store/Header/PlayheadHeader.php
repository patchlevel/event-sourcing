<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Header;

use Patchlevel\EventSourcing\Attribute\Header;

/** @immutable */
#[Header('playhead')]
final class PlayheadHeader
{
    /** @param positive-int $playhead */
    public function __construct(
        public readonly int $playhead,
    ) {
    }
}
