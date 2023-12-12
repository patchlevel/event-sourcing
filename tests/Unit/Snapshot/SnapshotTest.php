<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot;

use Patchlevel\EventSourcing\Snapshot\Snapshot;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSnapshot;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Snapshot\Snapshot */
final class SnapshotTest extends TestCase
{
    public function testSnapshot(): void
    {
        $snapshot = new Snapshot(
            ProfileWithSnapshot::class,
            '1',
            1,
            ['foo' => 'bar'],
        );

        self::assertSame(ProfileWithSnapshot::class, $snapshot->aggregate());
        self::assertSame('1', $snapshot->id());
        self::assertSame(1, $snapshot->playhead());
        self::assertSame(['foo' => 'bar'], $snapshot->payload());
    }
}
