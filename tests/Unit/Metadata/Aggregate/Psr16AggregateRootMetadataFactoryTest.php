<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\Psr16AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\CacheKey;
use Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate\Fixture\Profile2;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

#[CoversClass(Psr16AggregateRootMetadataFactory::class)]
final class Psr16AggregateRootMetadataFactoryTest extends TestCase
{
    public function testMetadataIsCached(): void
    {
        $metadata = new AggregateRootMetadata(
            Profile::class,
            'profile',
            'id',
            [],
            [],
            false,
            null,
        );

        $innerFactory = $this->createMock(AggregateRootMetadataFactory::class);
        $innerFactory->expects(self::once())
            ->method('metadata')
            ->with(Profile::class)
            ->willReturn($metadata);

        $cache = new Psr16Cache(new ArrayAdapter());
        $factory = new Psr16AggregateRootMetadataFactory($innerFactory, $cache);

        self::assertSame($metadata, $factory->metadata(Profile::class));
        self::assertTrue($cache->has(CacheKey::forAggregateRoot(Profile::class)));
        self::assertEquals($metadata, $factory->metadata(Profile::class));
    }

    public function testMetadataIgnoresEntryOfOtherAggregate(): void
    {
        $metadata = new AggregateRootMetadata(
            Profile::class,
            'profile',
            'id',
            [],
            [],
            false,
            null,
        );
        $otherMetadata = new AggregateRootMetadata(
            Profile2::class,
            'profile2',
            'id',
            [],
            [],
            false,
            null,
        );

        $innerFactory = $this->createMock(AggregateRootMetadataFactory::class);
        $innerFactory->expects(self::once())
            ->method('metadata')
            ->with(Profile::class)
            ->willReturn($metadata);

        $cache = new Psr16Cache(new ArrayAdapter());
        $cache->set(CacheKey::forAggregateRoot(Profile::class), $otherMetadata);

        $factory = new Psr16AggregateRootMetadataFactory($innerFactory, $cache);

        self::assertSame($metadata, $factory->metadata(Profile::class));
    }
}
