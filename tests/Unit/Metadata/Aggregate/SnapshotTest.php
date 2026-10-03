<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\Snapshot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Snapshot::class)]
final class SnapshotTest extends TestCase
{
    public function testInstantiate(): void
    {
        $snapshot = new Snapshot('default', 10, '1');

        self::assertSame('default', $snapshot->store);
        self::assertSame(10, $snapshot->batch);
        self::assertSame('1', $snapshot->version);
    }

    public function testInstantiateWithDefaults(): void
    {
        $snapshot = new Snapshot('default');

        self::assertSame('default', $snapshot->store);
        self::assertNull($snapshot->batch);
        self::assertNull($snapshot->version);
    }
}
