<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\PersonalData\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Integration\PersonalData\ProfileId;
use Patchlevel\Hydrator\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Attribute\PersonalData;

#[Event('profile.name_changed')]
final class NameChanged
{
    public function __construct(
        #[DataSubjectId]
        public readonly ProfileId $aggregateId,
        #[PersonalData(fallback: 'unknown')]
        public readonly string $name,
    ) {
    }
}
