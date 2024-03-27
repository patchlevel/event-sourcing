<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber\ArgumentResolver;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\RecordedOnArgumentResolver;
use PHPUnit\Framework\TestCase;
use stdClass;

/** @covers \Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\RecordedOnArgumentResolver */
final class RecordedOnArgumentResolverTest extends TestCase
{
    public function testSupport(): void
    {
        $resolver = new RecordedOnArgumentResolver();

        self::assertTrue(
            $resolver->support(
                new ArgumentMetadata('foo', DateTimeImmutable::class),
                'qux',
            ),
        );

        self::assertFalse(
            $resolver->support(
                new ArgumentMetadata('foo', 'bar'),
                'qux',
            ),
        );
    }

    public function testResolve(): void
    {
        $date = new DateTimeImmutable();

        $resolver = new RecordedOnArgumentResolver();
        $message = (new Message(new stdClass()))->withHeader(
            new AggregateHeader(
                'foo',
                'bar',
                1,
                $date,
            ),
        );

        self::assertSame(
            $date,
            $resolver->resolve(
                new ArgumentMetadata('foo', DateTimeImmutable::class),
                $message,
            ),
        );
    }
}
