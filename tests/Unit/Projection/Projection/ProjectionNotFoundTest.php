<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection;

use Patchlevel\EventSourcing\Projection\Projection\ProjectionNotFound;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projection\ProjectionNotFound */
final class ProjectionNotFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new ProjectionNotFound('foo-1');

        self::assertSame(
            'projection with the id "foo-1" not found',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
