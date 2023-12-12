<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('profile_visited')]
final class ProfileVisited
{
    public function __construct(
        #[ProfileIdNormalizer]
        public ProfileId $visitorId,
    ) {
    }
}
