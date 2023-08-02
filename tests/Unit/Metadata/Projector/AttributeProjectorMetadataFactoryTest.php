<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Projector;

use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Metadata\Projector\AttributeProjectorMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Projector\DuplicateCreateMethod;
use Patchlevel\EventSourcing\Metadata\Projector\DuplicateDropMethod;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projector\Projector;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

final class AttributeProjectorMetadataFactoryTest extends TestCase
{
    public function testEmptyProjection(): void
    {
        $projection = new class implements Projector {
            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('foo', 1);
            }
        };

        $metadataFactory = new AttributeProjectorMetadataFactory();
        $metadata = $metadataFactory->metadata($projection::class);

        self::assertSame([], $metadata->handleMethods);
        self::assertNull($metadata->createMethod);
        self::assertNull($metadata->dropMethod);
    }

    public function testStandardProjection(): void
    {
        $projection = new class implements Projector {
            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('foo', 1);
            }

            #[Handle(ProfileVisited::class)]
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
            $metadata->handleMethods,
        );

        self::assertSame('create', $metadata->createMethod);
        self::assertSame('drop', $metadata->dropMethod);
    }

    public function testMultipleHandlerOnOneMethod(): void
    {
        $projection = new class implements Projector {
            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('foo', 1);
            }

            #[Handle(ProfileVisited::class)]
            #[Handle(ProfileCreated::class)]
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
            $metadata->handleMethods,
        );
    }

    public function testDuplicateCreateAttributeException(): void
    {
        $this->expectException(DuplicateCreateMethod::class);

        $projection = new class implements Projector {
            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('foo', 1);
            }

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

        $projection = new class implements Projector {
            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('foo', 1);
            }

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
