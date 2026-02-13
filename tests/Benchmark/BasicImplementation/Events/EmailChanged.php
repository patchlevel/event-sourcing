<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\DataSubjectId;
use Patchlevel\Hydrator\Extension\Cryptography\Attribute\SensitiveData;

#[Event('profile.email_changed')]
final class EmailChanged
{
    public function __construct(
        #[DataSubjectId]
        public ProfileId $profileId,
        #[SensitiveData]
        public string|null $email,
    ) {
    }
}
