<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;

#[Event('profile_visited')]
final class ProfileVisited
{
    public function __construct(
        #[IdNormalizer]
        public ProfileId $visitorId,
    ) {
    }
}
