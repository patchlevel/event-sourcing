<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events;

use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\EventNormalizer\ProfileIdNormalizer;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;

final class ProfileCreated
{
    public function __construct(
        #[Normalize(ProfileIdNormalizer::class)]
        public ProfileId $profileId,
        public string $name
    ) {
    }
}
