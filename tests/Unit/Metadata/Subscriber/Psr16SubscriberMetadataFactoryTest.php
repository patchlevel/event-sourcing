<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\CacheKey;
use Patchlevel\EventSourcing\Metadata\Subscriber\Psr16SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\BatchingSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

#[CoversClass(Psr16SubscriberMetadataFactory::class)]
final class Psr16SubscriberMetadataFactoryTest extends TestCase
{
    public function testMetadataIsCached(): void
    {
        $metadata = new SubscriberMetadata('batching');

        $innerFactory = $this->createMock(SubscriberMetadataFactory::class);
        $innerFactory->expects(self::once())
            ->method('metadata')
            ->with(BatchingSubscriber::class)
            ->willReturn($metadata);

        $cache = new Psr16Cache(new ArrayAdapter());
        $factory = new Psr16SubscriberMetadataFactory($innerFactory, $cache);

        self::assertSame($metadata, $factory->metadata(BatchingSubscriber::class));
        self::assertTrue($cache->has(CacheKey::forSubscriber(BatchingSubscriber::class)));
        self::assertEquals($metadata, $factory->metadata(BatchingSubscriber::class));
    }

    public function testMetadataIgnoresInvalidEntry(): void
    {
        $metadata = new SubscriberMetadata('batching');

        $innerFactory = $this->createMock(SubscriberMetadataFactory::class);
        $innerFactory->expects(self::once())
            ->method('metadata')
            ->with(BatchingSubscriber::class)
            ->willReturn($metadata);

        $cache = new Psr16Cache(new ArrayAdapter());
        $cache->set(CacheKey::forSubscriber(BatchingSubscriber::class), 'invalid');

        $factory = new Psr16SubscriberMetadataFactory($innerFactory, $cache);

        self::assertSame($metadata, $factory->metadata(BatchingSubscriber::class));
    }
}
