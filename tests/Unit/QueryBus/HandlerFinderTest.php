<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\QueryBus;

use Patchlevel\EventSourcing\Attribute\Answer;
use Patchlevel\EventSourcing\QueryBus\HandlerFinder;
use Patchlevel\EventSourcing\QueryBus\HandlerReference;
use Patchlevel\EventSourcing\QueryBus\InvalidHandleMethod;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\OtherQueryProfile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\QueryProfile;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\QueryBus\HandlerFinder */
final class HandlerFinderTest extends TestCase
{
    public function testNoParameters(): void
    {
        $this->expectException(InvalidHandleMethod::class);

        $class = new class () {
            #[Answer]
            public function handle(): void
            {
            }
        };

        $result = [...HandlerFinder::findInClass($class::class)];
        self::assertSame([], $result);
    }

    public function testNoType(): void
    {
        $this->expectException(InvalidHandleMethod::class);

        $class = new class () {
            // phpcs:disable
            #[Answer]
            public function handle(mixed $query): void
            {
            }
            // phpcs:enable
        };

        $result = [...HandlerFinder::findInClass($class::class)];

        self::assertSame([], $result);
    }

    public function testWrongType(): void
    {
        $this->expectException(InvalidHandleMethod::class);

        $class = new class () {
            #[Answer]
            public function handle(string $query): void
            {
            }
        };

        $result = [...HandlerFinder::findInClass($class::class)];

        self::assertSame([], $result);
    }

    public function testEmpty(): void
    {
        $class = new class () {
        };

        $result = [...HandlerFinder::findInClass($class::class)];

        self::assertSame([], $result);
    }

    public function testWithqueryClass(): void
    {
        $class = new class () {
            #[Answer(QueryProfile::class)]
            public function handle(QueryProfile $query): void
            {
            }

            #[Answer(OtherQueryProfile::class)]
            public function handleOther(OtherQueryProfile $query): void
            {
            }
        };

        $result = [...HandlerFinder::findInClass($class::class)];

        self::assertEquals(
            [
                new HandlerReference(QueryProfile::class, 'handle', false),
                new HandlerReference(OtherQueryProfile::class, 'handleOther', false),
            ],
            $result,
        );
    }

    public function testWithTypeGuessing(): void
    {
        $class = new class () {
            #[Answer]
            public function handle(QueryProfile $query): void
            {
            }
        };

        $result = [...HandlerFinder::findInClass($class::class)];

        self::assertEquals(
            [
                new HandlerReference(QueryProfile::class, 'handle', false),
            ],
            $result,
        );
    }
}
