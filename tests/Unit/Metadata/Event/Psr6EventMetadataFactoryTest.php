<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\Event\EventMetadata;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\Psr6EventMetadataFactory;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

#[CoversClass(Psr6EventMetadataFactory::class)]
final class Psr6EventMetadataFactoryTest extends TestCase
{
    public function testCacheHit(): void
    {
        $value = new EventMetadata('profile.created');

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
            ->with(ProfileCreated::class)
            ->willReturn($item);
        $cache
            ->expects($this->never())
            ->method('save');

        $innerFactory = $this->createMock(EventMetadataFactory::class);
        $innerFactory
            ->expects($this->never())
            ->method('metadata');

        $factory = new Psr6EventMetadataFactory($innerFactory, $cache);

        self::assertSame($value, $factory->metadata(ProfileCreated::class));
    }

    public function testCacheMiss(): void
    {
        $value = new EventMetadata('profile.created');

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
            ->with(ProfileCreated::class)
            ->willReturn($item);
        $cache
            ->expects($this->once())
            ->method('save')
            ->with($item)
            ->willReturn(true);

        $innerFactory = $this->createMock(EventMetadataFactory::class);
        $innerFactory
            ->expects($this->once())
            ->method('metadata')
            ->with(ProfileCreated::class)
            ->willReturn($value);

        $factory = new Psr6EventMetadataFactory($innerFactory, $cache);

        self::assertSame($value, $factory->metadata(ProfileCreated::class));
    }
}
