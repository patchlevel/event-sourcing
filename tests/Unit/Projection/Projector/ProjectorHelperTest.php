<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projector;

use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorHelper;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projector\ProjectorHelper */
final class ProjectorHelperTest extends TestCase
{
    public function testProjectionName(): void
    {
        $projector = new #[Projector('dummy')]
        class {
        };

        $helper = new ProjectorHelper();

        self::assertSame('dummy', $helper->name($projector));
    }

    public function testProjectionVersion(): void
    {
        $projector = new #[Projector('dummy', 1)]
        class {
        };

        $helper = new ProjectorHelper();

        self::assertSame(1, $helper->version($projector));
    }

    public function testProjectionId(): void
    {
        $projector = new #[Projector('dummy', 1)]
        class {
        };

        $helper = new ProjectorHelper();

        self::assertEquals(new ProjectionId('dummy', 1), $helper->projectorId($projector));
    }
}
