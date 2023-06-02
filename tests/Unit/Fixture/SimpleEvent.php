<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('simple')]
final class SimpleEvent
{
    public string|null $name = null;
}
