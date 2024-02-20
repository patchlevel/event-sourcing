<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection;

use Patchlevel\EventSourcing\Projection\Projection\ProjectionAlreadyExists;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projection\ProjectionAlreadyExists */
final class ProjectionAlreadyExistsTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new ProjectionAlreadyExists('foo-1');

        self::assertSame(
            'Projection "foo-1" already exists',
            $exception->getMessage(),
        );

        self::assertSame(0, $exception->getCode());
    }
}
