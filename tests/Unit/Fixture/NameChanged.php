<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

final class NameChanged
{
    public function __construct(
        public string $name
    ) {
    }
}
