<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection;

use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\ProfileCreated;

#[Projector('noop_profile')]
final class NoopProjector
{
    #[Setup]
    public function create(): void
    {
    }

    #[Teardown]
    public function drop(): void
    {
    }

    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(ProfileCreated $profileCreated): void
    {
    }

    #[Subscribe(NameChanged::class)]
    public function onNameChanged(NameChanged $nameChanged): void
    {
    }
}
