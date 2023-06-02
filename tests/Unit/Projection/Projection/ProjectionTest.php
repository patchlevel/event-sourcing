<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection;

use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projection\Projection */
final class ProjectionTest extends TestCase
{
    public function testCreate(): void
    {
        $id = new ProjectionId('test', 1);
        $projection = new Projection($id);

        self::assertSame($id, $projection->id());
        self::assertEquals(ProjectionStatus::New, $projection->status());
        self::assertEquals(0, $projection->position());
        self::assertTrue($projection->isNew());
        self::assertFalse($projection->isBooting());
        self::assertFalse($projection->isActive());
        self::assertFalse($projection->isError());
        self::assertFalse($projection->isOutdated());
    }

    public function testBooting(): void
    {
        $projection = new Projection(
            new ProjectionId('test', 1),
        );

        $projection->booting();

        self::assertEquals(ProjectionStatus::Booting, $projection->status());
        self::assertFalse($projection->isNew());
        self::assertTrue($projection->isBooting());
        self::assertFalse($projection->isActive());
        self::assertFalse($projection->isError());
        self::assertFalse($projection->isOutdated());
    }

    public function testActive(): void
    {
        $projection = new Projection(
            new ProjectionId('test', 1),
        );

        $projection->active();

        self::assertEquals(ProjectionStatus::Active, $projection->status());
        self::assertFalse($projection->isNew());
        self::assertFalse($projection->isBooting());
        self::assertTrue($projection->isActive());
        self::assertFalse($projection->isError());
        self::assertFalse($projection->isOutdated());
    }

    public function testError(): void
    {
        $projection = new Projection(
            new ProjectionId('test', 1),
        );

        $projection->error();

        self::assertEquals(ProjectionStatus::Error, $projection->status());
        self::assertFalse($projection->isNew());
        self::assertFalse($projection->isBooting());
        self::assertFalse($projection->isActive());
        self::assertTrue($projection->isError());
        self::assertFalse($projection->isOutdated());
    }

    public function testOutdated(): void
    {
        $projection = new Projection(
            new ProjectionId('test', 1),
        );

        $projection->outdated();

        self::assertEquals(ProjectionStatus::Outdated, $projection->status());
        self::assertFalse($projection->isNew());
        self::assertFalse($projection->isBooting());
        self::assertFalse($projection->isActive());
        self::assertFalse($projection->isError());
        self::assertTrue($projection->isOutdated());
    }

    public function testIncrementPosition(): void
    {
        $projection = new Projection(
            new ProjectionId('test', 1),
        );

        $projection->incrementPosition();

        self::assertEquals(1, $projection->position());
    }
}
