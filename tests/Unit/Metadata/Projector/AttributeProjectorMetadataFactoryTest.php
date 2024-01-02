<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Projector;

use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Metadata\Projector\AttributeProjectorMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Projector\ClassIsNotAProjector;
use Patchlevel\EventSourcing\Metadata\Projector\DuplicateCreateMethod;
use Patchlevel\EventSourcing\Metadata\Projector\DuplicateDropMethod;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

final class AttributeProjectorMetadataFactoryTest extends TestCase
{
    public function testNotAProjection(): void
    {
        $this->expectException(ClassIsNotAProjector::class);

        $projection = new class {
        };

        $metadataFactory = new AttributeProjectorMetadataFactory();
        $metadataFactory->metadata($projection::class);
    }

    public function testEmptyProjection(): void
    {
        $projection = new #[Projector('foo', 1)]
        class {
        };

        $metadataFactory = new AttributeProjectorMetadataFactory();
        $metadata = $metadataFactory->metadata($projection::class);

        self::assertSame([], $metadata->subscribeMethods);
        self::assertNull($metadata->createMethod);
        self::assertNull($metadata->dropMethod);
        self::assertSame('foo', $metadata->name);
        self::assertSame(1, $metadata->version);
    }

    public function testStandardProjection(): void
    {
        $projection = new #[Projector('foo', 1)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function handle(): void
            {
            }

            #[Create]
            public function create(): void
            {
            }

            #[Drop]
            public function drop(): void
            {
            }
        };

        $metadataFactory = new AttributeProjectorMetadataFactory();
        $metadata = $metadataFactory->metadata($projection::class);

        self::assertEquals(
            [ProfileVisited::class => 'handle'],
            $metadata->subscribeMethods,
        );

        self::assertSame('create', $metadata->createMethod);
        self::assertSame('drop', $metadata->dropMethod);
    }

    public function testMultipleHandlerOnOneMethod(): void
    {
        $projection = new #[Projector('foo', 1)]
        class {
            #[Subscribe(ProfileVisited::class)]
            #[Subscribe(ProfileCreated::class)]
            public function handle(): void
            {
            }
        };

        $metadataFactory = new AttributeProjectorMetadataFactory();
        $metadata = $metadataFactory->metadata($projection::class);

        self::assertEquals(
            [
                ProfileVisited::class => 'handle',
                ProfileCreated::class => 'handle',
            ],
            $metadata->subscribeMethods,
        );
    }

    public function testDuplicateCreateAttributeException(): void
    {
        $this->expectException(DuplicateCreateMethod::class);

        $projection = new #[Projector('foo', 1)]
        class {
            #[Create]
            public function create1(): void
            {
            }

            #[Create]
            public function create2(): void
            {
            }
        };

        $metadataFactory = new AttributeProjectorMetadataFactory();
        $metadataFactory->metadata($projection::class);
    }

    public function testDuplicateDropAttributeException(): void
    {
        $this->expectException(DuplicateDropMethod::class);

        $projection = new #[Projector('foo', 1)]
        class {
            #[Drop]
            public function drop1(): void
            {
            }

            #[Drop]
            public function drop2(): void
            {
            }
        };

        $metadataFactory = new AttributeProjectorMetadataFactory();
        $metadataFactory->metadata($projection::class);
    }
}
