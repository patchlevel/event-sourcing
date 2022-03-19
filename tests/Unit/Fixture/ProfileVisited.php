<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

final class ProfileVisited
{
    public function __construct(
        public ProfileId $visitorId
    ) {
    }
}
