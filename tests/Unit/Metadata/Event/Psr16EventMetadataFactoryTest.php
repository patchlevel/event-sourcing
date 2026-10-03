<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\Event\EventMetadata;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\Psr16EventMetadataFactory;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

#[CoversClass(Psr16EventMetadataFactory::class)]
final class Psr16EventMetadataFactoryTest extends TestCase
{
    public function testCacheHit(): void
    {
        $value = new EventMetadata('profile.created');

        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->once())
            ->method('get')
            ->with(ProfileCreated::class)
            ->willReturn($value);
        $cache
            ->expects($this->never())
            ->method('set');

        $innerFactory = $this->createMock(EventMetadataFactory::class);
        $innerFactory
            ->expects($this->never())
            ->method('metadata');

        $factory = new Psr16EventMetadataFactory($innerFactory, $cache);

        self::assertSame($value, $factory->metadata(ProfileCreated::class));
    }

    public function testCacheMiss(): void
    {
        $value = new EventMetadata('profile.created');

        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->once())
            ->method('get')
            ->with(ProfileCreated::class)
            ->willReturn(null);
        $cache
            ->expects($this->once())
            ->method('set')
            ->with(ProfileCreated::class, $value)
            ->willReturn(true);

        $innerFactory = $this->createMock(EventMetadataFactory::class);
        $innerFactory
            ->expects($this->once())
            ->method('metadata')
            ->with(ProfileCreated::class)
            ->willReturn($value);

        $factory = new Psr16EventMetadataFactory($innerFactory, $cache);

        self::assertSame($value, $factory->metadata(ProfileCreated::class));
    }
}
