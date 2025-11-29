<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Header;

use DateTimeImmutable;

/** @immutable */
final class RecordedOnHeader
{
    public function __construct(
        public readonly DateTimeImmutable $recordedOn,
    ) {
    }
}
