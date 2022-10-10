<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Projectionist\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Normalizer\ProfileIdNormalizer;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\ProfileId;

#[Event('profile.created')]
final class ProfileCreated
{
    public function __construct(
        #[Normalize(new ProfileIdNormalizer())]
        public ProfileId $profileId,
        public string $name
    ) {
    }
}
