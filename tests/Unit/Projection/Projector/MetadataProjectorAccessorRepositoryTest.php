<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projector;

use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Metadata\Projector\AttributeProjectorMetadataFactory;
use Patchlevel\EventSourcing\Projection\Projector\MetadataProjectorAccessor;
use Patchlevel\EventSourcing\Projection\Projector\MetadataProjectorAccessorRepository;
use PHPUnit\Framework\TestCase;

final class MetadataProjectorAccessorRepositoryTest extends TestCase
{
    public function testEmpty(): void
    {
        $repository = new MetadataProjectorAccessorRepository(
            [],
        );

        self::assertEquals([], $repository->all());
        self::assertNull($repository->get('foo'));
    }

    public function testWithProjector(): void
    {
        $projector = new #[Projector('foo')]
        class {
        };
        $metadataFactory = new AttributeProjectorMetadataFactory();

        $repository = new MetadataProjectorAccessorRepository(
            [$projector],
            $metadataFactory,
        );

        $accessor = new MetadataProjectorAccessor(
            $projector,
            $metadataFactory->metadata($projector::class),
        );

        self::assertEquals([$accessor], $repository->all());
        self::assertEquals($accessor, $repository->get('foo'));
    }
}
