<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository;

use Patchlevel\EventSourcing\Repository\SnapshotRebuildFailed;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;

#[CoversClass(SnapshotRebuildFailed::class)]
final class SnapshotRebuildFailedTest extends TestCase
{
    public function testCreate(): void
    {
        $id = ProfileId::fromString('1');
        $previous = new RuntimeException('rebuild error');

        $exception = new SnapshotRebuildFailed(Profile::class, $id, $previous);

        self::assertSame(
            sprintf('Rebuild from snapshot of aggregate "%s" with the id "1" failed', Profile::class),
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertSame(Profile::class, $exception->aggregateClass());
        self::assertSame($id, $exception->aggregateRootId());
    }
}
