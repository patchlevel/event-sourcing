<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Projection\DefaultProjectionHandler;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Projection\DefaultProjectionHandler */
final class DefaultProjectionRepositoryTest extends TestCase
{
    use ProphecyTrait;

    public function testHandleWithNoProjections(): void
    {
        $projectionRepository = new DefaultProjectionHandler([]);
        $projectionRepository->handle(ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('profile@test.com')
        ));

        $this->expectNotToPerformAssertions();
    }

    public function testHandle(): void
    {
        $projection = new class implements Projection {
            public static ?AggregateChanged $handledEvent = null;

            #[Handle(ProfileCreated::class)]
            public function handleProfileCreated(ProfileCreated $event): void
            {
                self::$handledEvent = $event;
            }
        };

        $profileCreated = ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('profile@test.com')
        );

        $projectionRepository = new DefaultProjectionHandler([$projection]);
        $projectionRepository->handle($profileCreated);

        self::assertSame($profileCreated, $projection::$handledEvent);
    }

    public function testHandleNotSupportedEvent(): void
    {
        $projection = new class implements Projection {
            public static ?AggregateChanged $handledEvent = null;

            #[Handle(ProfileCreated::class)]
            public function handleProfileCreated(ProfileCreated $event): void
            {
                self::$handledEvent = $event;
            }
        };

        $profileVisited = ProfileVisited::raise(
            ProfileId::fromString('1'),
            ProfileId::fromString('2'),
        );

        $projectionRepository = new DefaultProjectionHandler([$projection]);
        $projectionRepository->handle($profileVisited);

        self::assertNull($projection::$handledEvent);
    }

    public function testCreate(): void
    {
        $projection = new class implements Projection {
            public static bool $called = false;

            #[Create]
            public function method(): void
            {
                self::$called = true;
            }
        };

        $projectionRepository = new DefaultProjectionHandler([$projection]);
        $projectionRepository->create();

        self::assertTrue($projection::$called);
    }

    public function testDrop(): void
    {
        $projection = new class implements Projection {
            public static bool $called = false;

            #[Drop]
            public function method(): void
            {
                self::$called = true;
            }
        };

        $projectionRepository = new DefaultProjectionHandler([$projection]);
        $projectionRepository->drop();

        self::assertTrue($projection::$called);
    }
}
