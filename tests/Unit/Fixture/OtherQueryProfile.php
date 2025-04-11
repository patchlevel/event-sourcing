<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

final class OtherQueryProfile
{
    public function __construct(
        public readonly ProfileId $id,
    ) {
    }
}
