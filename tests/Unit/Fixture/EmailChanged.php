<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\DataSubjectId;
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\PersonalData;

#[Event('email_changed')]
final class EmailChanged
{
    public function __construct(
        #[DataSubjectId]
        public string $id,
        #[PersonalData('fallback')]
        public string $email,
    ) {
    }
}
