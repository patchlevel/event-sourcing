<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection;

use Patchlevel\EventSourcing\Projection\Projection\DuplicateProjectionId;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projection\DuplicateProjectionId */
final class DuplicateProjectionIdTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new DuplicateProjectionId(new ProjectionId('foo', 1));

        self::assertSame(
            'projection with the id "foo-1" exist already',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
