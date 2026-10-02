<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Header;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Attribute\Header;

/** @immutable */
#[Header('recordedOn')]
final class RecordedOnHeader
{
    public function __construct(
        public readonly DateTimeImmutable $recordedOn,
    ) {
    }
}
