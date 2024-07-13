<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\ChildAggregate\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Integration\ChildAggregate\ProfileId;

#[Event('profile.created')]
final class ProfileCreated
{
    public function __construct(
        public ProfileId $profileId,
        public string $name,
    ) {
    }
}
