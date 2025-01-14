<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\ProfileId;

#[Event('profile.admin_promoted')]
final class AdminPromoted
{
    public function __construct(
        public ProfileId $profileId,
    ) {
    }
}
