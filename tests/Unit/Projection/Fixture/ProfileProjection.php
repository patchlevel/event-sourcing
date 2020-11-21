<?php

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Fixture;

use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Tests\Unit\Aggregate\Fixture\ProfileCreated;

class ProfileProjection implements Projection
{
    public static function getHandledMessages(): iterable
    {
        yield ProfileCreated::class => 'applyProfileCreated';
    }

    public function drop(): void
    {
        // do nothinig
    }
}
