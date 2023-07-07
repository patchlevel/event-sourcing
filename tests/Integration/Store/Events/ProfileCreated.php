<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Store\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Integration\Store\Normalizer\ProfileIdNormalizer;
use Patchlevel\EventSourcing\Tests\Integration\Store\ProfileId;

#[Event('profile.created')]
final class ProfileCreated
{
    public function __construct(
        #[ProfileIdNormalizer]
        public ProfileId $profileId,
        public string $name,
    ) {
    }
}
