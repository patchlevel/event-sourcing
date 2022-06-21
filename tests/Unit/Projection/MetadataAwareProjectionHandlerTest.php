<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\MetadataAwareProjectionHandler;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Projection\MetadataAwareProjectionHandler */
final class MetadataAwareProjectionHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function testHandleWithNoProjections(): void
    {
        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('profile@test.com')
        );

        $message = new Message(
            $event
        );

        $projectionRepository = new MetadataAwareProjectionHandler([]);
        $projectionRepository->handle($message);

        $this->expectNotToPerformAssertions();
    }

    public function testHandle(): void
    {
        $projection = new class implements Projection {
            public static ?object $handledEvent = null;

            #[Handle(ProfileCreated::class)]
            public function handleProfileCreated(ProfileCreated $event): void
            {
                self::$handledEvent = $event;
            }
        };

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('profile@test.com')
        );

        $message = new Message(
            $event
        );

        $projectionRepository = new MetadataAwareProjectionHandler([$projection]);
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

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('profile@test.com')
        );

        $message = new Message(
            $event
        );

        $projectionRepository = new MetadataAwareProjectionHandler([$projection]);
        $projectionRepository->handle($message);

        self::assertSame($message, $projection::$handledMessage);
    }

    public function testHandleNotSupportedEvent(): void
    {
        $projection = new class implements Projection {
            public static ?object $handledEvent = null;

            #[Handle(ProfileCreated::class)]
            public function handleProfileCreated(ProfileCreated $event): void
            {
                self::$handledEvent = $event;
            }
        };

        $event = new ProfileVisited(
            ProfileId::fromString('1')
        );

        $message = new Message(
            $event
        );

        $projectionRepository = new MetadataAwareProjectionHandler([$projection]);
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

        $projectionRepository = new MetadataAwareProjectionHandler([$projection]);
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

        $projectionRepository = new MetadataAwareProjectionHandler([$projection]);
        $projectionRepository->drop();

        self::assertTrue($projection::$called);
    }
}
