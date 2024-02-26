<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\SplitStream;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;

#[Event('profile.reborn')]
#[SplitStream]
final class Reborn
{
    public function __construct(
        #[IdNormalizer]
        public ProfileId $profileId,
        public string $name,
    ) {
    }
}
