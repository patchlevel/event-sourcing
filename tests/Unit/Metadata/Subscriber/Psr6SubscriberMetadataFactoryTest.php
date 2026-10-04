<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\CacheKey;
use Patchlevel\EventSourcing\Metadata\Subscriber\Psr6SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\BatchingSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(Psr6SubscriberMetadataFactory::class)]
final class Psr6SubscriberMetadataFactoryTest extends TestCase
{
    public function testMetadataIsCached(): void
    {
        $metadata = new SubscriberMetadata('batching');

        $innerFactory = $this->createMock(SubscriberMetadataFactory::class);
        $innerFactory->expects(self::once())
            ->method('metadata')
            ->with(BatchingSubscriber::class)
            ->willReturn($metadata);

        $cache = new ArrayAdapter();
        $factory = new Psr6SubscriberMetadataFactory($innerFactory, $cache);

        self::assertSame($metadata, $factory->metadata(BatchingSubscriber::class));
        self::assertTrue($cache->hasItem(CacheKey::forSubscriber(BatchingSubscriber::class)));
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

        $cache = new ArrayAdapter();
        $item = $cache->getItem(CacheKey::forSubscriber(BatchingSubscriber::class));
        $item->set('invalid');
        $cache->save($item);

        $factory = new Psr6SubscriberMetadataFactory($innerFactory, $cache);

        self::assertSame($metadata, $factory->metadata(BatchingSubscriber::class));
    }
}
