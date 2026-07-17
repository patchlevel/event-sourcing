<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\SensitiveData\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Integration\SensitiveData\ProfileId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;

#[Event('profile.created')]
final class ProfileCreated
{
    public function __construct(
        #[DataSubjectId]
        public ProfileId $profileId,
        #[SensitiveData(fallback: 'unknown')]
        public string $name,
    ) {
    }
}
