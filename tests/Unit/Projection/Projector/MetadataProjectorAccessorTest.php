<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projector;

use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\Projector\AttributeProjectorMetadataFactory;
use Patchlevel\EventSourcing\Projection\Projection\RunMode;
use Patchlevel\EventSourcing\Projection\Projector\MetadataProjectorAccessor;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projector\MetadataProjectorAccessor */
final class MetadataProjectorAccessorTest extends TestCase
{
    public function testId(): void
    {
        $projector = new #[Projector('profile')]
        class {
        };

        $accessor = new MetadataProjectorAccessor(
            $projector,
            (new AttributeProjectorMetadataFactory())->metadata($projector::class),
        );

        self::assertEquals('profile', $accessor->id());
    }

    public function testGroup(): void
    {
        $projector = new #[Projector('profile')]
        class {
        };

        $accessor = new MetadataProjectorAccessor(
            $projector,
            (new AttributeProjectorMetadataFactory())->metadata($projector::class),
        );

        self::assertEquals('default', $accessor->group());
    }

    public function testRunMode(): void
    {
        $projector = new #[Projector('profile')]
        class {
        };

        $accessor = new MetadataProjectorAccessor(
            $projector,
            (new AttributeProjectorMetadataFactory())->metadata($projector::class),
        );

        self::assertEquals(RunMode::FromBeginning, $accessor->runMode());
    }

    public function testSubscribeMethod(): void
    {
        $projector = new #[Projector('profile')]
        class {
            #[Subscribe(ProfileCreated::class)]
            public function onProfileCreated(Message $message): void
            {
            }
        };

        $accessor = new MetadataProjectorAccessor(
            $projector,
            (new AttributeProjectorMetadataFactory())->metadata($projector::class),
        );

        $result = $accessor->subscribeMethods(ProfileCreated::class);

        self::assertEquals([
            $projector->onProfileCreated(...),
        ], $result);
    }

    public function testMultipleSubscribeMethod(): void
    {
        $projector = new #[Projector('profile')]
        class {
            #[Subscribe(ProfileCreated::class)]
            public function onProfileCreated(Message $message): void
            {
            }

            #[Subscribe(ProfileCreated::class)]
            public function onFoo(Message $message): void
            {
            }
        };

        $accessor = new MetadataProjectorAccessor(
            $projector,
            (new AttributeProjectorMetadataFactory())->metadata($projector::class),
        );

        $result = $accessor->subscribeMethods(ProfileCreated::class);

        self::assertEquals([
            $projector->onProfileCreated(...),
            $projector->onFoo(...),
        ], $result);
    }

    public function testSubscribeAllMethod(): void
    {
        $projector = new #[Projector('profile')]
        class {
            #[Subscribe('*')]
            public function onProfileCreated(Message $message): void
            {
            }
        };

        $accessor = new MetadataProjectorAccessor(
            $projector,
            (new AttributeProjectorMetadataFactory())->metadata($projector::class),
        );

        $result = $accessor->subscribeMethods(ProfileCreated::class);

        self::assertEquals([
            $projector->onProfileCreated(...),
        ], $result);
    }

    public function testSetupMethod(): void
    {
        $projector = new #[Projector('profile')]
        class {
            #[Setup]
            public function method(): void
            {
            }
        };

        $accessor = new MetadataProjectorAccessor(
            $projector,
            (new AttributeProjectorMetadataFactory())->metadata($projector::class),
        );

        $result = $accessor->setupMethod();

        self::assertEquals($projector->method(...), $result);
    }

    public function testNotSetupMethod(): void
    {
        $projector = new #[Projector('profile')]
        class {
        };

        $accessor = new MetadataProjectorAccessor(
            $projector,
            (new AttributeProjectorMetadataFactory())->metadata($projector::class),
        );

        $result = $accessor->setupMethod();

        self::assertNull($result);
    }

    public function testTeardownMethod(): void
    {
        $projector = new #[Projector('profile')]
        class {
            #[Teardown]
            public function method(): void
            {
            }
        };

        $accessor = new MetadataProjectorAccessor(
            $projector,
            (new AttributeProjectorMetadataFactory())->metadata($projector::class),
        );

        $result = $accessor->teardownMethod();

        self::assertEquals($projector->method(...), $result);
    }

    public function testNotTeardownMethod(): void
    {
        $projector = new #[Projector('profile')]
        class {
        };

        $accessor = new MetadataProjectorAccessor(
            $projector,
            (new AttributeProjectorMetadataFactory())->metadata($projector::class),
        );

        $result = $accessor->teardownMethod();

        self::assertNull($result);
    }
}
