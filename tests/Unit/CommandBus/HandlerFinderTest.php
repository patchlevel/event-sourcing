<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\CommandBus\HandlerFinder;
use Patchlevel\EventSourcing\CommandBus\HandlerReference;
use Patchlevel\EventSourcing\CommandBus\InvalidHandleMethod;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ChangeProfileName;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HandlerFinder::class)]
final class HandlerFinderTest extends TestCase
{
    public function testNoParameters(): void
    {
        $this->expectException(InvalidHandleMethod::class);

        $class = new class () {
            #[Handle]
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
            #[Handle]
            public function handle(mixed $command): void
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
            #[Handle]
            public function handle(string $command): void
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

    public function testWithCommandClass(): void
    {
        $class = new class () {
            #[Handle(CreateProfile::class)]
            public function handle(CreateProfile $command): void
            {
            }
        };

        $result = [...HandlerFinder::findInClass($class::class)];

        self::assertEquals([
            new HandlerReference(CreateProfile::class, 'handle', false),
        ], $result);
    }

    public function testWithTypeGuessing(): void
    {
        $class = new class () {
            #[Handle]
            public function handle(CreateProfile $command): void
            {
            }
        };

        $result = [...HandlerFinder::findInClass($class::class)];

        self::assertEquals([
            new HandlerReference(CreateProfile::class, 'handle', false),
        ], $result);
    }

    public function testWithUnionTypeGuessing(): void
    {
        $class = new class () {
            #[Handle]
            public function handle(CreateProfile|ChangeProfileName $command): void
            {
            }
        };

        $result = [...HandlerFinder::findInClass($class::class)];

        self::assertEquals([
            new HandlerReference(ChangeProfileName::class, 'handle', false),
            new HandlerReference(CreateProfile::class, 'handle', false),
        ], $result);
    }

    public function testWithMultipleAttributes(): void
    {
        $class = new class () {
            #[Handle(CreateProfile::class)]
            #[Handle(ChangeProfileName::class)]
            public function handle(CreateProfile $command): void
            {
            }
        };

        $result = [...HandlerFinder::findInClass($class::class)];

        self::assertEquals([
            new HandlerReference(CreateProfile::class, 'handle', false),
            new HandlerReference(ChangeProfileName::class, 'handle', false),
        ], $result);
    }
}
