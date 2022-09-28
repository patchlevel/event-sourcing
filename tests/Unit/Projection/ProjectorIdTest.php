<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Projection\ProjectorId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\ProjectorId */
class ProjectorIdTest extends TestCase
{
    public function testProjectorId(): void
    {
        $projectorId = new ProjectorId(
            'test',
            1
        );

        self::assertSame('test', $projectorId->name());
        self::assertSame(1, $projectorId->version());
        self::assertSame('test-1', $projectorId->toString());
    }
}
