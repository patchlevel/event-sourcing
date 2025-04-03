<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot\Adapter;

use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotNotFound;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SnapshotNotFound::class)]
final class SnapshotNotFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new SnapshotNotFound('foo');

        self::assertSame(
            'snapshot with the key "foo" not found',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
