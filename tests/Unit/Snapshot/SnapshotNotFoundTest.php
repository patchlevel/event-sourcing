<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot;

use Patchlevel\EventSourcing\Identifier\CustomIdBehaviour;
use Patchlevel\EventSourcing\Identifier\Identifier;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SnapshotNotFound::class)]
final class SnapshotNotFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new SnapshotNotFound(Profile::class, new class ('1') implements Identifier {
            use CustomIdBehaviour;
        });

        self::assertSame(
            'snapshot for aggregate "Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile" with the id "1" not found',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
