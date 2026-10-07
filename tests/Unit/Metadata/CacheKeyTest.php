<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata;

use Patchlevel\EventSourcing\Metadata\CacheKey;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\BatchingSubscriber;
use Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Metadata\Event\Fixture\EmailChanged;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheKey::class)]
final class CacheKeyTest extends TestCase
{
    public function testForAggregateRoot(): void
    {
        self::assertSame(
            'aggregate_root_metadata_9a0bf60bef5e8fb9122415286c9bcb7b',
            CacheKey::forAggregateRoot(Profile::class),
        );
    }

    public function testForEvent(): void
    {
        self::assertSame(
            'event_metadata_e236493fc51f7d5f1cc5208ff452b85b',
            CacheKey::forEvent(EmailChanged::class),
        );
    }

    public function testForSubscriber(): void
    {
        self::assertSame(
            'subscriber_metadata_09f581e7a4f322c1a9e4686dc8b3efa4',
            CacheKey::forSubscriber(BatchingSubscriber::class),
        );
    }
}
