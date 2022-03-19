<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events;

final class ProfileCreated
{
    public function __construct(
        public string $profileId,
        public string $name
    ) {
    }
}
