<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\EventNormalizer\ProfileIdNormalizer;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\ProfileId;

#[Event('profile.created')]
final class ProfileCreated
{
    public function __construct(
        #[ProfileIdNormalizer]
        public ProfileId $profileId,
    ) {
    }
}
