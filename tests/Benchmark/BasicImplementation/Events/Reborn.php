<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\SplitStream;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Normalizer\ProfileIdNormalizer;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;

#[Event('profile.reborn')]
#[SplitStream]
final class Reborn
{
    public function __construct(
        #[ProfileIdNormalizer]
        public ProfileId $profileId,
        public string $name,
    ) {
    }
}
