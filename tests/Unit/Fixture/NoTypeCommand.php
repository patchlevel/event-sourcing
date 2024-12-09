<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\HandledBy;

#[HandledBy(ProfileWithHandlers::class)]
final class NoTypeCommand
{
}
