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

        $state = new Projection(
            $id
        );

        self::assertSame($id, $state->id());
        self::assertEquals(ProjectionStatus::New, $state->status());
        self::assertEquals(0, $state->position());
        self::assertTrue($state->isNew());
        self::assertFalse($state->isBooting());
        self::assertFalse($state->isActive());
        self::assertFalse($state->isError());
        self::assertFalse($state->isOutdated());
    }

    public function testBooting(): void
    {
        $state = new Projection(
            new ProjectionId('test', 1)
        );

        $state->booting();

        self::assertEquals(ProjectionStatus::Booting, $state->status());
        self::assertFalse($state->isNew());
        self::assertTrue($state->isBooting());
        self::assertFalse($state->isActive());
        self::assertFalse($state->isError());
        self::assertFalse($state->isOutdated());
    }

    public function testActive(): void
    {
        $state = new Projection(
            new ProjectionId('test', 1)
        );

        $state->active();

        self::assertEquals(ProjectionStatus::Active, $state->status());
        self::assertFalse($state->isNew());
        self::assertFalse($state->isBooting());
        self::assertTrue($state->isActive());
        self::assertFalse($state->isError());
        self::assertFalse($state->isOutdated());
    }

    public function testError(): void
    {
        $state = new Projection(
            new ProjectionId('test', 1)
        );

        $state->error();

        self::assertEquals(ProjectionStatus::Error, $state->status());
        self::assertFalse($state->isNew());
        self::assertFalse($state->isBooting());
        self::assertFalse($state->isActive());
        self::assertTrue($state->isError());
        self::assertFalse($state->isOutdated());
    }

    public function testOutdated(): void
    {
        $state = new Projection(
            new ProjectionId('test', 1)
        );

        $state->outdated();

        self::assertEquals(ProjectionStatus::Outdated, $state->status());
        self::assertFalse($state->isNew());
        self::assertFalse($state->isBooting());
        self::assertFalse($state->isActive());
        self::assertFalse($state->isError());
        self::assertTrue($state->isOutdated());
    }

    public function testIncrementPosition(): void
    {
        $state = new Projection(
            new ProjectionId('test', 1)
        );

        $state->incrementPosition();

        self::assertEquals(1, $state->position());
    }
}
