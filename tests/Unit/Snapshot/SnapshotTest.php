<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot;

use Patchlevel\EventSourcing\Snapshot\Snapshot;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\TestCase;

class SnapshotTest extends TestCase
{
    public function testSnapshot(): void
    {
        $snapshot = new Snapshot(
            Profile::class,
            '1',
            0,
            ['foo' => 'bar']
        );

        self::assertEquals(Profile::class, $snapshot->aggregate());
        self::assertEquals('1', $snapshot->id());
        self::assertEquals(0, $snapshot->playhead());
        self::assertEquals(['foo' => 'bar'], $snapshot->payload());
    }
}
