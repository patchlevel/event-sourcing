<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\ChildAggregate\Events;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('profile.view_tracked')]
final class ViewTracked
{
    public function __construct()
    {
    }
}
