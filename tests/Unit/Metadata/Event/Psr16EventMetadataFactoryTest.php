<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\CacheKey;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadata;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\Psr16EventMetadataFactory;
use Patchlevel\EventSourcing\Tests\Unit\Metadata\Event\Fixture\EmailChanged;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

#[CoversClass(Psr16EventMetadataFactory::class)]
final class Psr16EventMetadataFactoryTest extends TestCase
{
    public function testMetadataIsCached(): void
    {
        $metadata = new EventMetadata('email_changed');

        $innerFactory = $this->createMock(EventMetadataFactory::class);
        $innerFactory->expects(self::once())
            ->method('metadata')
            ->with(EmailChanged::class)
            ->willReturn($metadata);

        $cache = new Psr16Cache(new ArrayAdapter());
        $factory = new Psr16EventMetadataFactory($innerFactory, $cache);

        self::assertSame($metadata, $factory->metadata(EmailChanged::class));
        self::assertTrue($cache->has(CacheKey::forEvent(EmailChanged::class)));
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

        $cache = new Psr16Cache(new ArrayAdapter());
        $cache->set(CacheKey::forEvent(EmailChanged::class), 'invalid');

        $factory = new Psr16EventMetadataFactory($innerFactory, $cache);

        self::assertSame($metadata, $factory->metadata(EmailChanged::class));
    }
}
