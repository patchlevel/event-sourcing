<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Id;

final class ActivateProfile
{
    public function __construct(
        #[Id]
        public readonly ProfileId $id,
    ) {
    }
}
