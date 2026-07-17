<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\SensitiveData\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Integration\SensitiveData\ProfileId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;

#[Event('profile.name_changed')]
final class NameChanged
{
    public function __construct(
        #[DataSubjectId]
        public readonly ProfileId $aggregateId,
        #[SensitiveData(fallback: 'unknown')]
        public readonly string $name,
    ) {
    }
}
