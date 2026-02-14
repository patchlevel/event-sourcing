<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\MessageArgumentResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(MessageArgumentResolver::class)]
final class MessageArgumentResolverTest extends TestCase
{
    public function testSupport(): void
    {
        $resolver = new MessageArgumentResolver();

        self::assertTrue(
            $resolver->support(
                new ArgumentMetadata('foo', Message::class, false),
                'qux',
            ),
        );

        self::assertFalse(
            $resolver->support(
                new ArgumentMetadata('foo', 'bar', false),
                'qux',
            ),
        );
    }

    public function testResolve(): void
    {
        $resolver = new MessageArgumentResolver();
        $message = new Message(new stdClass());

        self::assertSame(
            $message,
            $resolver->resolve(
                new ArgumentMetadata('foo', Message::class, false),
                $message,
            ),
        );
    }
}
