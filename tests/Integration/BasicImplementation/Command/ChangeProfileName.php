<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Command;

use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\ProfileId;

final class ChangeProfileName
{
    public function __construct(
        #[Id]
        public readonly ProfileId $id,
        public readonly string $name,
    ) {
    }
}
