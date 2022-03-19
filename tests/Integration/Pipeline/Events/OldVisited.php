<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events;

final class OldVisited
{
    public function __construct(
        public string $profileId
    ) {
    }
}
