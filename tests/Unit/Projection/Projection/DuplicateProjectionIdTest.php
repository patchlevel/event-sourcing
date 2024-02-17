<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection;

use Patchlevel\EventSourcing\Projection\Projection\DuplicateProjectionId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projection\DuplicateProjectionId */
final class DuplicateProjectionIdTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new DuplicateProjectionId('foo-1');

        self::assertSame(
            'projection with the id "foo-1" exist already',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
