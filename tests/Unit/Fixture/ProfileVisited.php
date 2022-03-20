<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Normalize;

final class ProfileVisited
{
    public function __construct(
        #[Normalize(ProfileIdNormalizer::class)]
        public ProfileId $visitorId
    ) {
    }
}
