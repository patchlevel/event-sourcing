<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot;

use Patchlevel\EventSourcing\Snapshot\SnapshotNotConfigured;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Snapshot\SnapshotNotConfigured */
final class SnapshotNotConfiguredTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new SnapshotNotConfigured(Profile::class);

        self::assertSame(
            'Missing snapshot configuration for the aggregate class "Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile"',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
