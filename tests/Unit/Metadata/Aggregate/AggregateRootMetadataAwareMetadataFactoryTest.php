<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Identifier\Identifier;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataAwareMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateWithoutMetadataAware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(AggregateRootMetadataAwareMetadataFactory::class)]
final class AggregateRootMetadataAwareMetadataFactoryTest extends TestCase
{
    public function testMetadata(): void
    {
        $factory = new AggregateRootMetadataAwareMetadataFactory();

        $metadata = $factory->metadata(Profile::class);

        self::assertSame(Profile::class, $metadata->className);
        self::assertSame('profile', $metadata->name);
    }

    public function testMetadataWithoutMetadataAware(): void
    {
        $aggregate = new class implements AggregateRoot {
            public function aggregateRootId(): Identifier
            {
                throw new RuntimeException('not implemented');
            }

            /** @param iterable<object> $events */
            public function catchUp(iterable $events): void
            {
            }

            /** @return list<object> */
            public function releaseEvents(): array
            {
                return [];
            }

            /**
             * @param iterable<object> $events
             * @param 0|positive-int   $startPlayhead
             */
            public static function createFromEvents(iterable $events, int $startPlayhead = 0): static
            {
                throw new RuntimeException('not implemented');
            }

            public function playhead(): int
            {
                return 0;
            }
        };

        $factory = new AggregateRootMetadataAwareMetadataFactory();

        $this->expectException(AggregateWithoutMetadataAware::class);

        $factory->metadata($aggregate::class);
    }
}
