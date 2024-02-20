<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection;

use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionError;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use Patchlevel\EventSourcing\Projection\Projection\ThrowableToErrorContextTransformer;
use Patchlevel\EventSourcing\Projection\RetryStrategy\Retry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @covers \Patchlevel\EventSourcing\Projection\Projection\Projection */
final class ProjectionTest extends TestCase
{
    public function testCreate(): void
    {
        $id = 'test';
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
            'test',
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
            'test',
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
            'test',
        );

        $exception = new RuntimeException('test');

        $projection->error($exception);

        self::assertEquals(ProjectionStatus::Error, $projection->status());
        self::assertFalse($projection->isNew());
        self::assertFalse($projection->isBooting());
        self::assertFalse($projection->isActive());
        self::assertTrue($projection->isError());
        self::assertFalse($projection->isOutdated());
        self::assertEquals(
            new ProjectionError(
                'test',
                ProjectionStatus::New,
                ThrowableToErrorContextTransformer::transform($exception),
            ),
            $projection->projectionError(),
        );
    }

    public function testOutdated(): void
    {
        $projection = new Projection(
            'test',
        );

        $projection->outdated();

        self::assertEquals(ProjectionStatus::Outdated, $projection->status());
        self::assertFalse($projection->isNew());
        self::assertFalse($projection->isBooting());
        self::assertFalse($projection->isActive());
        self::assertFalse($projection->isError());
        self::assertTrue($projection->isOutdated());
    }

    public function testChangePosition(): void
    {
        $projection = new Projection(
            'test',
        );

        $projection->changePosition(10);

        self::assertEquals(10, $projection->position());
    }

    public function testRetry(): void
    {
        $projection = new Projection(
            'test',
        );

        self::assertEquals(null, $projection->retry());

        $retry = new Retry(1, null);

        $projection->updateRetry($retry);

        self::assertEquals($retry, $projection->retry());

        $projection->resetRetry();

        self::assertEquals(null, $projection->retry());
    }
}
