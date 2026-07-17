<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\Psr16AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

#[CoversClass(Psr16AggregateRootMetadataFactory::class)]
final class Psr16AggregateRootMetadataFactoryTest extends TestCase
{
    public function testCacheHit(): void
    {
        $value = new AggregateRootMetadata(Profile::class, 'profile', 'id', [], [], false, null);

        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->once())
            ->method('get')
            ->with(Profile::class)
            ->willReturn($value);
        $cache
            ->expects($this->never())
            ->method('set');

        $innerFactory = $this->createMock(AggregateRootMetadataFactory::class);
        $innerFactory
            ->expects($this->never())
            ->method('metadata');

        $factory = new Psr16AggregateRootMetadataFactory($innerFactory, $cache);

        self::assertSame($value, $factory->metadata(Profile::class));
    }

    public function testCacheMiss(): void
    {
        $value = new AggregateRootMetadata(Profile::class, 'profile', 'id', [], [], false, null);

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

        $innerFactory = $this->createMock(AggregateRootMetadataFactory::class);
        $innerFactory
            ->expects($this->once())
            ->method('metadata')
            ->with(Profile::class)
            ->willReturn($value);

        $factory = new Psr16AggregateRootMetadataFactory($innerFactory, $cache);

        self::assertSame($value, $factory->metadata(Profile::class));
    }
}
