<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Events;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('profile.admin_promoted')]
final class AdminPromoted
{
}
