<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\HandledBy;
use Patchlevel\EventSourcing\Attribute\Id;

#[HandledBy(ProfileWithHandlers::class)]
final class ChangeProfileName
{
    public function __construct(
        #[Id]
        public readonly ProfileId $id,
        public readonly string $name,
    ) {
    }
}
