<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\EventNormalizer\ProfileIdNormalizer;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\ProfileId;

#[Event('profile.created')]
final class ProfileCreated
{
    public function __construct(
        #[Normalize(ProfileIdNormalizer::class)]
        public ProfileId $profileId,
        public string $name
    ) {
    }
}
