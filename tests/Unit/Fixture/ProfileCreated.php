<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('profile_created')]
final class ProfileCreated
{
    public function __construct(
        #[ProfileIdNormalizer]
        public ProfileId $profileId,
        #[EmailNormalizer]
        public Email $email,
    ) {
    }
}
