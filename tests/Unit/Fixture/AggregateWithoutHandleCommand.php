<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\HandledBy;

#[HandledBy(Profile::class)]
final class AggregateWithoutHandleCommand
{
}
