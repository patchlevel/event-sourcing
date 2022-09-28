<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\ProjectorStore;

use Patchlevel\EventSourcing\Projection\ProjectorId;
use Patchlevel\EventSourcing\Projection\ProjectorStatus;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorState;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorState */
class ProjectorStateTest extends TestCase
{
    public function testCreate(): void
    {
        $id = new ProjectorId('test', 1);

        $state = new ProjectorState(
            $id
        );

        self::assertSame($id, $state->id());
        self::assertEquals(ProjectorStatus::New, $state->status());
        self::assertEquals(0, $state->position());
        self::assertTrue($state->isNew());
        self::assertFalse($state->isBooting());
        self::assertFalse($state->isActive());
        self::assertFalse($state->isError());
        self::assertFalse($state->isOutdated());
    }

    public function testBooting(): void
    {
        $state = new ProjectorState(
            new ProjectorId('test', 1)
        );

        $state->booting();

        self::assertEquals(ProjectorStatus::Booting, $state->status());
        self::assertFalse($state->isNew());
        self::assertTrue($state->isBooting());
        self::assertFalse($state->isActive());
        self::assertFalse($state->isError());
        self::assertFalse($state->isOutdated());
    }

    public function testActive(): void
    {
        $state = new ProjectorState(
            new ProjectorId('test', 1)
        );

        $state->active();

        self::assertEquals(ProjectorStatus::Active, $state->status());
        self::assertFalse($state->isNew());
        self::assertFalse($state->isBooting());
        self::assertTrue($state->isActive());
        self::assertFalse($state->isError());
        self::assertFalse($state->isOutdated());
    }

    public function testError(): void
    {
        $state = new ProjectorState(
            new ProjectorId('test', 1)
        );

        $state->error();

        self::assertEquals(ProjectorStatus::Error, $state->status());
        self::assertFalse($state->isNew());
        self::assertFalse($state->isBooting());
        self::assertFalse($state->isActive());
        self::assertTrue($state->isError());
        self::assertFalse($state->isOutdated());
    }

    public function testOutdated(): void
    {
        $state = new ProjectorState(
            new ProjectorId('test', 1)
        );

        $state->outdated();

        self::assertEquals(ProjectorStatus::Outdated, $state->status());
        self::assertFalse($state->isNew());
        self::assertFalse($state->isBooting());
        self::assertFalse($state->isActive());
        self::assertFalse($state->isError());
        self::assertTrue($state->isOutdated());
    }

    public function testIncrementPosition(): void
    {
        $state = new ProjectorState(
            new ProjectorId('test', 1)
        );

        $state->incrementPosition();

        self::assertEquals(1, $state->position());
    }
}
