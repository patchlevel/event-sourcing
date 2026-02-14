<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Lookup\Lookup;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\LookupResolver;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LookupResolver::class)]
final class LookupResolverTest extends TestCase
{
    public function testSupport(): void
    {
        $store = $this->createMock(Store::class);
        $eventRegistry = new EventRegistry([]);

        $resolver = new LookupResolver($store, $eventRegistry);

        self::assertTrue(
            $resolver->support(
                new ArgumentMetadata('lookup', Lookup::class, false),
                ProfileCreated::class,
            ),
        );

        self::assertFalse(
            $resolver->support(
                new ArgumentMetadata('foo', ProfileCreated::class, false),
                ProfileCreated::class,
            ),
        );
    }

    public function testResolve(): void
    {
        $event = new ProfileVisited(ProfileId::fromString('1'));

        $store = $this->createMock(Store::class);
        $eventRegistry = new EventRegistry([]);

        $resolver = new LookupResolver($store, $eventRegistry);

        $message = (new Message($event))->withHeader(
            new IndexHeader(1),
        );

        $lookup = $resolver->resolve(
            new ArgumentMetadata('foo', Lookup::class, false),
            $message,
        );

        self::assertInstanceOf(Lookup::class, $lookup);
    }
}
