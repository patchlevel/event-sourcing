<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription;

use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Aggregate\RamseyUuidV7Behaviour;

final class ProfileId implements AggregateRootId
{
    use RamseyUuidV7Behaviour;
}
