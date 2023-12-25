<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projector;

use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Projection;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorHelper;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projector\ProjectorHelper */
final class ProjectorHelperTest extends TestCase
{
    public function testHandle(): void
    {
        $projector = new #[Projection('dummy')]
        class {
            public static Message|null $handledMessage = null;

            #[Subscribe(ProfileCreated::class)]
            public function handleProfileCreated(Message $message): void
            {
                self::$handledMessage = $message;
            }
        };

        $event = new ProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('profile@test.com'),
        );

        $message = new Message(
            $event,
        );

        $helper = new ProjectorHelper();
        $helper->handleMessage($message, $projector);

        self::assertSame($message, $projector::$handledMessage);
    }

    public function testHandleNotSupportedEvent(): void
    {
        $projector = new #[Projection('dummy')]
        class {
            public static Message|null $handledMessage = null;

            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('dummy', 1);
            }

            #[Subscribe(ProfileCreated::class)]
            public function handleProfileCreated(Message $message): void
            {
                self::$handledMessage = $message;
            }
        };

        $event = new ProfileVisited(
            ProfileId::fromString('1'),
        );

        $message = new Message(
            $event,
        );

        $helper = new ProjectorHelper();
        $helper->handleMessage($message, $projector);

        self::assertNull($projector::$handledMessage);
    }

    public function testCreate(): void
    {
        $projector = new #[Projection('dummy')]
        class {
            public static bool $called = false;

            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('dummy', 1);
            }

            #[Create]
            public function method(): void
            {
                self::$called = true;
            }
        };

        $helper = new ProjectorHelper();
        $helper->createProjection($projector);

        self::assertTrue($projector::$called);
    }

    public function testDrop(): void
    {
        $projector = new #[Projection('dummy')]
        class {
            public static bool $called = false;

            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('dummy', 1);
            }

            #[Drop]
            public function method(): void
            {
                self::$called = true;
            }
        };

        $helper = new ProjectorHelper();
        $helper->dropProjection($projector);

        self::assertTrue($projector::$called);
    }
}
