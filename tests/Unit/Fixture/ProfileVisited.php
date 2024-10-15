<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;
use Stringable;

#[Event('profile_visited')]
final class ProfileVisited implements Stringable
{
    public function __construct(
        #[IdNormalizer]
        public ProfileId $visitorId,
    ) {
    }

    public function __toString(): string
    {
        return $this->visitorId->toString();
    }
}
