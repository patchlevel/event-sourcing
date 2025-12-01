<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;

#[Event('profile.name_changed')]
final class NameChanged
{
    public function __construct(
        public ProfileId $profileId,
        public string $name,
    ) {
    }
}
