<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\PersonalData\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Integration\PersonalData\ProfileId;
use Patchlevel\Hydrator\Attribute\DataSubjectId as LegacyDataSubjectId;
use Patchlevel\Hydrator\Attribute\PersonalData;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;

#[Event('profile.name_changed')]
final class NameChanged
{
    public function __construct(
        #[DataSubjectId]
        #[LegacyDataSubjectId]
        public readonly ProfileId $aggregateId,
        #[SensitiveData(fallback: 'unknown')]
        #[PersonalData(fallback: 'unknown')]
        public readonly string $name,
    ) {
    }
}
