<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\SplitStream;

#[SplitStream]
#[Event('splitting_event')]
final class SplittingEvent
{
    public function __construct(
        public Email $email,
        public int $visits,
    ) {
    }
}
