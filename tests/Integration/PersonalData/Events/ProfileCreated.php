<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\PersonalData\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Integration\PersonalData\ProfileId;
use Patchlevel\Hydrator\Attribute\DataSubjectId as LegacyDataSubjectId;
use Patchlevel\Hydrator\Attribute\PersonalData;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;

#[Event('profile.created')]
final class ProfileCreated
{
    public function __construct(
        #[DataSubjectId]
        #[LegacyDataSubjectId]
        public ProfileId $profileId,
        #[SensitiveData(fallback: 'unknown')]
        #[PersonalData(fallback: 'unknown')]
        public string $name,
    ) {
    }
}
