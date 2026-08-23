<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\Psr6AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

#[CoversClass(Psr6AggregateRootMetadataFactory::class)]
final class Psr6AggregateRootMetadataFactoryTest extends TestCase
{
    public function testCacheHit(): void
    {
        $value = new AggregateRootMetadata(Profile::class, 'profile', 'id', [], [], false, null);

        $item = $this->createMock(CacheItemInterface::class);
        $item
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $item
            ->expects($this->once())
            ->method('get')
            ->willReturn($value);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache
            ->expects($this->once())
            ->method('getItem')
            ->with(Profile::class)
            ->willReturn($item);
        $cache
            ->expects($this->never())
            ->method('save');

        $innerFactory = $this->createMock(AggregateRootMetadataFactory::class);
        $innerFactory
            ->expects($this->never())
            ->method('metadata');

        $factory = new Psr6AggregateRootMetadataFactory($innerFactory, $cache);

        self::assertSame($value, $factory->metadata(Profile::class));
    }

    public function testCacheMiss(): void
    {
        $value = new AggregateRootMetadata(Profile::class, 'profile', 'id', [], [], false, null);

        $item = $this->createMock(CacheItemInterface::class);
        $item
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $item
            ->expects($this->once())
            ->method('set')
            ->with($value)
            ->willReturnSelf();

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache
            ->expects($this->once())
            ->method('getItem')
            ->with(Profile::class)
            ->willReturn($item);
        $cache
            ->expects($this->once())
            ->method('save')
            ->with($item)
            ->willReturn(true);

        $innerFactory = $this->createMock(AggregateRootMetadataFactory::class);
        $innerFactory
            ->expects($this->once())
            ->method('metadata')
            ->with(Profile::class)
            ->willReturn($value);

        $factory = new Psr6AggregateRootMetadataFactory($innerFactory, $cache);

        self::assertSame($value, $factory->metadata(Profile::class));
    }
}
