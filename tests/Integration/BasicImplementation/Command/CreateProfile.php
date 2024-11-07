<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Command;

use Patchlevel\EventSourcing\Attribute\HandledBy;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\ProfileId;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\ProfileWithCommands;

#[HandledBy(ProfileWithCommands::class)]
final class CreateProfile
{
    public function __construct(
        public readonly ProfileId $id,
        public readonly string $name,
    ) {
    }
}
