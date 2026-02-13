<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\SensitiveData\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Integration\SensitiveData\ProfileId;

#[Event('profile.personal_data_removed')]
final class PersonalDataRemoved
{
    public function __construct(
        public readonly ProfileId $profileId,
    ) {
    }
}
