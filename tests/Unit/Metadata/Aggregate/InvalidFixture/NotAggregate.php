<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate\InvalidFixture;

use Patchlevel\EventSourcing\Attribute\Aggregate;

#[Aggregate('not_aggregate')]
final class NotAggregate
{
}
