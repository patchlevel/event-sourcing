<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot;

use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Aggregate\CustomIdBehaviour;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Snapshot\SnapshotNotFound */
final class SnapshotNotFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new SnapshotNotFound(Profile::class, new class ('1') implements AggregateRootId {
            use CustomIdBehaviour;
        });

        self::assertSame(
            'snapshot for aggregate "Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile" with the id "1" not found',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
