<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Command;

use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\ProfileId;

final class CreateProfile
{
    public function __construct(
        public readonly ProfileId $id,
        public readonly string $name,
    ) {
    }
}
