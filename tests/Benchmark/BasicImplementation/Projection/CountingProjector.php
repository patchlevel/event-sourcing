<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection;

use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\ProfileCreated;

/** The same as the partitioned counting projector, but as a single subscription. */
#[Projector('counting')]
final class CountingProjector
{
    public int $handled = 0;

    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(ProfileCreated $event): void
    {
        $this->handled++;
    }

    #[Subscribe(NameChanged::class)]
    public function onNameChanged(NameChanged $event): void
    {
        $this->handled++;
    }
}
