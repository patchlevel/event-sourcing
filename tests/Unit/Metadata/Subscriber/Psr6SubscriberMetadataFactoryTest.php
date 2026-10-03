<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\Subscriber\Psr6SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

#[CoversClass(Psr6SubscriberMetadataFactory::class)]
final class Psr6SubscriberMetadataFactoryTest extends TestCase
{
    public function testCacheHit(): void
    {
        $value = new SubscriberMetadata('foo');

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

        $innerFactory = $this->createMock(SubscriberMetadataFactory::class);
        $innerFactory
            ->expects($this->never())
            ->method('metadata');

        $factory = new Psr6SubscriberMetadataFactory($innerFactory, $cache);

        self::assertSame($value, $factory->metadata(Profile::class));
    }

    public function testCacheMiss(): void
    {
        $value = new SubscriberMetadata('foo');

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

        $innerFactory = $this->createMock(SubscriberMetadataFactory::class);
        $innerFactory
            ->expects($this->once())
            ->method('metadata')
            ->with(Profile::class)
            ->willReturn($value);

        $factory = new Psr6SubscriberMetadataFactory($innerFactory, $cache);

        self::assertSame($value, $factory->metadata(Profile::class));
    }
}
