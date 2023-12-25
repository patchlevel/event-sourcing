<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projector;

use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Projection;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projector\MetadataProjectorResolver;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projector\MetadataProjectorResolver */
final class MetadataProjectorResolverTest extends TestCase
{
    public function testResolveHandleMethod(): void
    {
        $projection = new #[Projection('dummy')]
        class {
            public static Message|null $handledMessage = null;

            #[Subscribe(ProfileCreated::class)]
            public function handleProfileCreated(Message $message): void
            {
                self::$handledMessage = $message;
            }
        };

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('profile@test.com'),
            ),
        );

        $resolver = new MetadataProjectorResolver();
        $result = $resolver->resolveSubscribeMethod($projection, $message);

        self::assertIsCallable($result);

        $result($message);

        self::assertSame($message, $projection::$handledMessage);
    }

    public function testNotResolveHandleMethod(): void
    {
        $projection = new #[Projection('dummy')]
        class {
        };

        $message = new Message(
            new ProfileVisited(
                ProfileId::fromString('1'),
            ),
        );

        $resolver = new MetadataProjectorResolver();
        $result = $resolver->resolveSubscribeMethod($projection, $message);

        self::assertNull($result);
    }

    public function testResolveCreateMethod(): void
    {
        $projection = new #[Projection('dummy')]
        class {
            public static bool $called = false;

            #[Create]
            public function method(): void
            {
                self::$called = true;
            }
        };

        $resolver = new MetadataProjectorResolver();
        $result = $resolver->resolveCreateMethod($projection);

        self::assertIsCallable($result);

        $result();

        self::assertTrue($projection::$called);
    }

    public function testNotResolveCreateMethod(): void
    {
        $projection = new #[Projection('dummy')]
        class {
        };

        $resolver = new MetadataProjectorResolver();
        $result = $resolver->resolveCreateMethod($projection);

        self::assertNull($result);
    }

    public function testResolveDropMethod(): void
    {
        $projection = new #[Projection('dummy')]
        class {
            public static bool $called = false;

            #[Drop]
            public function method(): void
            {
                self::$called = true;
            }
        };

        $resolver = new MetadataProjectorResolver();
        $result = $resolver->resolveDropMethod($projection);

        self::assertIsCallable($result);

        $result();

        self::assertTrue($projection::$called);
    }

    public function testNotResolveDropMethod(): void
    {
        $projection = new #[Projection('dummy')]
        class {
        };

        $resolver = new MetadataProjectorResolver();
        $result = $resolver->resolveDropMethod($projection);

        self::assertNull($result);
    }

    public function testProjectionId(): void
    {
        $projection = new #[Projection('dummy', 1)]
        class {
        };

        $resolver = new MetadataProjectorResolver();
        $result = $resolver->projectionId($projection);

        self::assertEquals('dummy', $result->name());
        self::assertEquals(1, $result->version());
    }
}
