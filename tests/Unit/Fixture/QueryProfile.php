<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

final class QueryProfile
{
    public function __construct(
        public readonly ProfileId $id,
    ) {
    }
}
