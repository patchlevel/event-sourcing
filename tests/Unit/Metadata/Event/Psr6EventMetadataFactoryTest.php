<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\CacheKey;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadata;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\Psr6EventMetadataFactory;
use Patchlevel\EventSourcing\Tests\Unit\Metadata\Event\Fixture\EmailChanged;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(Psr6EventMetadataFactory::class)]
final class Psr6EventMetadataFactoryTest extends TestCase
{
    public function testMetadataIsCached(): void
    {
        $metadata = new EventMetadata('email_changed');

        $innerFactory = $this->createMock(EventMetadataFactory::class);
        $innerFactory->expects(self::once())
            ->method('metadata')
            ->with(EmailChanged::class)
            ->willReturn($metadata);

        $cache = new ArrayAdapter();
        $factory = new Psr6EventMetadataFactory($innerFactory, $cache);

        self::assertSame($metadata, $factory->metadata(EmailChanged::class));
        self::assertTrue($cache->hasItem(CacheKey::forEvent(EmailChanged::class)));
        self::assertEquals($metadata, $factory->metadata(EmailChanged::class));
    }

    public function testMetadataIgnoresInvalidEntry(): void
    {
        $metadata = new EventMetadata('email_changed');

        $innerFactory = $this->createMock(EventMetadataFactory::class);
        $innerFactory->expects(self::once())
            ->method('metadata')
            ->with(EmailChanged::class)
            ->willReturn($metadata);

        $cache = new ArrayAdapter();
        $item = $cache->getItem(CacheKey::forEvent(EmailChanged::class));
        $item->set('invalid');
        $cache->save($item);

        $factory = new Psr6EventMetadataFactory($innerFactory, $cache);

        self::assertSame($metadata, $factory->metadata(EmailChanged::class));
    }
}
