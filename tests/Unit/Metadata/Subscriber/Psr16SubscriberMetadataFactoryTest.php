<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\Subscriber\Psr16SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

#[CoversClass(Psr16SubscriberMetadataFactory::class)]
final class Psr16SubscriberMetadataFactoryTest extends TestCase
{
    public function testCacheHit(): void
    {
        $value = new SubscriberMetadata('foo');

        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->once())
            ->method('get')
            ->with(Profile::class)
            ->willReturn($value);
        $cache
            ->expects($this->never())
            ->method('set');

        $innerFactory = $this->createMock(SubscriberMetadataFactory::class);
        $innerFactory
            ->expects($this->never())
            ->method('metadata');

        $factory = new Psr16SubscriberMetadataFactory($innerFactory, $cache);

        self::assertSame($value, $factory->metadata(Profile::class));
    }

    public function testCacheMiss(): void
    {
        $value = new SubscriberMetadata('foo');

        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->once())
            ->method('get')
            ->with(Profile::class)
            ->willReturn(null);
        $cache
            ->expects($this->once())
            ->method('set')
            ->with(Profile::class, $value)
            ->willReturn(true);

        $innerFactory = $this->createMock(SubscriberMetadataFactory::class);
        $innerFactory
            ->expects($this->once())
            ->method('metadata')
            ->with(Profile::class)
            ->willReturn($value);

        $factory = new Psr16SubscriberMetadataFactory($innerFactory, $cache);

        self::assertSame($value, $factory->metadata(Profile::class));
    }
}
