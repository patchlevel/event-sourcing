<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection;

use Patchlevel\EventSourcing\Projection\Projection\ProjectionError;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @covers \Patchlevel\EventSourcing\Projection\Projection\ProjectionError */
final class ProjectionErrorTest extends TestCase
{
    public function testCreate(): void
    {
        $error = ProjectionError::fromThrowable(new RuntimeException('foo bar'));

        self::assertSame('foo bar', $error->errorMessage);
        self::assertIsArray($error->errorContext);
    }
}
