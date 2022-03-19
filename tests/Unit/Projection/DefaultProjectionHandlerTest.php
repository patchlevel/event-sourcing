<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\DefaultProjectionHandler;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Projection\DefaultProjectionHandler */
final class DefaultProjectionHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function testHandleWithNoProjections(): void
    {
        $event = ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('profile@test.com')
        );

        $message = new Message(
            Profile::class,
            '1',
            1,
            $event
        );

        $projectionRepository = new DefaultProjectionHandler([]);
        $projectionRepository->handle($message);

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

        $event = ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('profile@test.com')
        );

        $message = new Message(
            Profile::class,
            '1',
            1,
            $event
        );

        $projectionRepository = new DefaultProjectionHandler([$projection]);
        $projectionRepository->handle($message);

        self::assertSame($event, $projection::$handledEvent);
    }

    public function testHandleWithMessage(): void
    {
        $projection = new class implements Projection {
            public static ?Message $handledMessage = null;

            #[Handle(ProfileCreated::class)]
            public function handleProfileCreated(Message $message): void
            {
                self::$handledMessage = $message;
            }
        };

        $event = ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('profile@test.com')
        );

        $message = new Message(
            Profile::class,
            '1',
            1,
            $event
        );

        $projectionRepository = new DefaultProjectionHandler([$projection]);
        $projectionRepository->handle($message);

        self::assertSame($message, $projection::$handledMessage);
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

        $event = ProfileVisited::raise(
            ProfileId::fromString('1')
        );

        $message = new Message(
            Profile::class,
            '1',
            1,
            $event
        );

        $projectionRepository = new DefaultProjectionHandler([$projection]);
        $projectionRepository->handle($message);

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
