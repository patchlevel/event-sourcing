<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;
use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\PersonalData;

#[Event('profile.email_changed')]
final class EmailChanged
{
    public function __construct(
        #[DataSubjectId]
        public ProfileId $profileId,
        #[PersonalData]
        public string|null $email,
    ) {
    }
}
