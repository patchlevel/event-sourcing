<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber\ArgumentResolver;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\AggregateIdArgumentResolver;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\AggregateIdArgumentResolver */
final class AggregateIdArgumentResolverTest extends TestCase
{
    public function testSupport(): void
    {
        $resolver = new AggregateIdArgumentResolver();

        self::assertTrue(
            $resolver->support(
                new ArgumentMetadata('aggregateId', 'string'),
                ProfileCreated::class,
            ),
        );

        self::assertTrue(
            $resolver->support(
                new ArgumentMetadata('aggregateRootId', 'string'),
                ProfileCreated::class,
            ),
        );

        self::assertFalse(
            $resolver->support(
                new ArgumentMetadata('foo', 'string'),
                ProfileCreated::class,
            ),
        );
    }

    public function testResolve(): void
    {
        $event = new ProfileVisited(ProfileId::fromString('1'));

        $resolver = new AggregateIdArgumentResolver();
        $message = (new Message($event))->withHeader(
            new AggregateHeader('foo', 'bar', 1, new DateTimeImmutable()),
        );

        self::assertSame(
            'bar',
            $resolver->resolve(
                new ArgumentMetadata('foo', 'string'),
                $message,
            ),
        );
    }
}
