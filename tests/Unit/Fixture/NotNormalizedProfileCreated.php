<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('not_normalized_profile_created')]
final class NotNormalizedProfileCreated
{
    public function __construct(
        public ProfileId $profileId,
        public Email $email,
    ) {
    }
}
