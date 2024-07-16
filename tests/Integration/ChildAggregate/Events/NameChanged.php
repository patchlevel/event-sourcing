<?php

declare(strict_types=1);

namespace Integration\ChildAggregate\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Integration\ChildAggregate\ProfileId;

#[Event('profile.name_changed')]
final class NameChanged
{
    public function __construct(
        public string $name,
    ) {
    }
}
