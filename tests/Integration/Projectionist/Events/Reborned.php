<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Projectionist\Events;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('profile.reborned')]
final class Reborned
{
    public function __construct(
        public string $name,
    ) {
    }
}
