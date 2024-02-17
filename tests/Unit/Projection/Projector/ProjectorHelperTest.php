<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projector;

use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorHelper;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projector\ProjectorHelper */
final class ProjectorHelperTest extends TestCase
{
    public function testProjectionId(): void
    {
        $projector = new #[Projector('dummy')]
        class {
        };

        $helper = new ProjectorHelper();

        self::assertEquals('dummy', $helper->projectorId($projector));
    }
}
