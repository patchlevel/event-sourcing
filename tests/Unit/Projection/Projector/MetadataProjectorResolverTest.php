<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projector;

use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
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
        $projection = new #[Projector('dummy')]
        class {
            #[Subscribe(ProfileCreated::class)]
            public function handleProfileCreated(Message $message): void
            {
            }
        };

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('profile@test.com'),
            ),
        );

        $resolver = new MetadataProjectorResolver();
        $result = $resolver->resolveSubscribeMethods($projection, $message);

        self::assertEquals(
            [
                $projection->handleProfileCreated(...),
            ],
            $result,
        );
    }

    public function testResolveHandleAll(): void
    {
        $projection = new #[Projector('dummy')]
        class {
            #[Subscribe(Subscribe::ALL)]
            public function handleProfileCreated(Message $message): void
            {
            }
        };

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('profile@test.com'),
            ),
        );

        $resolver = new MetadataProjectorResolver();
        $result = $resolver->resolveSubscribeMethods($projection, $message);

        self::assertEquals(
            [
                $projection->handleProfileCreated(...),
            ],
            $result,
        );
    }

    public function testNotResolveHandleMethod(): void
    {
        $projection = new #[Projector('dummy')]
        class {
        };

        $message = new Message(
            new ProfileVisited(
                ProfileId::fromString('1'),
            ),
        );

        $resolver = new MetadataProjectorResolver();
        $result = $resolver->resolveSubscribeMethods($projection, $message);

        self::assertEmpty($result);
    }

    public function testResolveCreateMethod(): void
    {
        $projection = new #[Projector('dummy')]
        class {
            public static bool $called = false;

            #[Setup]
            public function method(): void
            {
                self::$called = true;
            }
        };

        $resolver = new MetadataProjectorResolver();
        $result = $resolver->resolveSetupMethod($projection);

        self::assertIsCallable($result);

        $result();

        self::assertTrue($projection::$called);
    }

    public function testNotResolveCreateMethod(): void
    {
        $projection = new #[Projector('dummy')]
        class {
        };

        $resolver = new MetadataProjectorResolver();
        $result = $resolver->resolveSetupMethod($projection);

        self::assertNull($result);
    }

    public function testResolveDropMethod(): void
    {
        $projection = new #[Projector('dummy')]
        class {
            public static bool $called = false;

            #[Teardown]
            public function method(): void
            {
                self::$called = true;
            }
        };

        $resolver = new MetadataProjectorResolver();
        $result = $resolver->resolveTeardownMethod($projection);

        self::assertIsCallable($result);

        $result();

        self::assertTrue($projection::$called);
    }

    public function testNotResolveDropMethod(): void
    {
        $projection = new #[Projector('dummy')]
        class {
        };

        $resolver = new MetadataProjectorResolver();
        $result = $resolver->resolveTeardownMethod($projection);

        self::assertNull($result);
    }

    public function testProjectionId(): void
    {
        $projector = new #[Projector('dummy')]
        class {
        };

        $resolver = new MetadataProjectorResolver();

        self::assertEquals('dummy', $resolver->projectorId($projector));
    }
}
