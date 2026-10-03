<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber\ArgumentResolver;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolverContext;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\RecordedOnArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\TypeInfo\Type;

#[CoversClass(RecordedOnArgumentResolver::class)]
final class RecordedOnArgumentResolverTest extends TestCase
{
    public function testSupport(): void
    {
        $resolver = new RecordedOnArgumentResolver();

        self::assertTrue(
            $resolver->support(
                new ArgumentMetadata('foo', Type::object(DateTimeImmutable::class)),
                'qux',
            ),
        );

        self::assertFalse(
            $resolver->support(
                new ArgumentMetadata('foo', Type::string()),
                'qux',
            ),
        );
    }

    public function testResolveFromAggregateHeader(): void
    {
        $date = new DateTimeImmutable();

        $resolver = new RecordedOnArgumentResolver();
        $message = (new Message(new stdClass()))->withHeader(
            new RecordedOnHeader($date),
        );

        self::assertSame(
            $date,
            $resolver->resolve(
                new ArgumentMetadata('foo', Type::object(DateTimeImmutable::class)),
                new ArgumentResolverContext($message, new Subscription('foo'), new SubscriberMetadata('foo')),
            ),
        );
    }

    public function testResolveFromRecordedOnHeader(): void
    {
        $date = new DateTimeImmutable();

        $resolver = new RecordedOnArgumentResolver();
        $message = (new Message(new stdClass()))->withHeader(new RecordedOnHeader($date));

        self::assertSame(
            $date,
            $resolver->resolve(
                new ArgumentMetadata('foo', Type::object(DateTimeImmutable::class)),
                new ArgumentResolverContext($message, new Subscription('foo'), new SubscriberMetadata('foo')),
            ),
        );
    }
}
