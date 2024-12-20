<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

final class CreateProfile
{
    public function __construct(
        public readonly ProfileId $id,
        public readonly string $name,
    ) {
    }
}
