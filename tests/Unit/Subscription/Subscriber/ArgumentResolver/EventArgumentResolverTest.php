<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\EventArgumentResolver;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;

#[CoversClass(EventArgumentResolver::class)]
final class EventArgumentResolverTest extends TestCase
{
    public function testSupport(): void
    {
        $resolver = new EventArgumentResolver();

        self::assertTrue(
            $resolver->support(
                new ArgumentMetadata('foo', Type::object(ProfileCreated::class)),
                ProfileCreated::class,
            ),
        );

        self::assertFalse(
            $resolver->support(
                new ArgumentMetadata('foo', Type::object(ProfileVisited::class)),
                ProfileCreated::class,
            ),
        );
    }

    public function testResolve(): void
    {
        $event = new ProfileVisited(ProfileId::fromString('1'));

        $resolver = new EventArgumentResolver();
        $message = new Message($event);

        self::assertSame(
            $event,
            $resolver->resolve(
                new ArgumentMetadata('foo', Type::object(ProfileVisited::class)),
                $message,
            ),
        );
    }
}
