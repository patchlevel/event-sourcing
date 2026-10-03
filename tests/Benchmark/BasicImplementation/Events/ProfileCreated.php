<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\EventTag;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;

#[Event('profile.created')]
final class ProfileCreated
{
    public function __construct(
        #[DataSubjectId]
        #[EventTag(prefix: 'profile')]
        public ProfileId $profileId,
        public string $name,
        #[SensitiveData]
        public string|null $email,
    ) {
    }
}
