<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot;

use Patchlevel\EventSourcing\Snapshot\SnapshotVersionInvalid;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Snapshot\SnapshotVersionInvalid */
final class SnapshotVersionInvalidTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new SnapshotVersionInvalid('foo');

        self::assertSame(
            'snapshot version with the key "foo" is invalid',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
