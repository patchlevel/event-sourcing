<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\ChildAggregate\Events;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('profile.name_changed')]
final class NameChanged
{
    public function __construct(
        public string $name,
    ) {
    }
}
