<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolverContext;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\EventEmitterResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\EventEmitter\DefaultEventEmitter;
use Patchlevel\EventSourcing\Subscription\Subscriber\EventEmitter\EventEmitter;
use Patchlevel\EventSourcing\Subscription\Subscriber\EventEmitter\NoopEventEmitter;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\TypeInfo\Type;

#[CoversClass(EventEmitterResolver::class)]
final class EventEmitterResolverTest extends TestCase
{
    public function testSupport(): void
    {
        $resolver = new EventEmitterResolver($this->createMock(Store::class));

        self::assertTrue(
            $resolver->support(
                new ArgumentMetadata('emitter', Type::object(EventEmitter::class)),
                ProfileVisited::class,
            ),
        );

        self::assertFalse(
            $resolver->support(
                new ArgumentMetadata('foo', Type::object(ProfileVisited::class)),
                ProfileVisited::class,
            ),
        );
    }

    public function testResolveDuringRunReturnsDefaultEmitter(): void
    {
        $resolver = new EventEmitterResolver($this->createMock(Store::class));

        $emitter = $resolver->resolve(
            new ArgumentMetadata('emitter', Type::object(EventEmitter::class)),
            $this->context(Status::Active),
        );

        self::assertInstanceOf(DefaultEventEmitter::class, $emitter);
    }

    public function testResolveDuringBootReturnsNoopByDefault(): void
    {
        $resolver = new EventEmitterResolver($this->createMock(Store::class));

        $emitter = $resolver->resolve(
            new ArgumentMetadata('emitter', Type::object(EventEmitter::class)),
            $this->context(Status::Booting),
        );

        self::assertInstanceOf(NoopEventEmitter::class, $emitter);
    }

    public function testEnableEventEmittingDuringBoot(): void
    {
        $resolver = new EventEmitterResolver($this->createMock(Store::class));

        $emitter = $resolver->resolve(
            new ArgumentMetadata('emitter', Type::object(EventEmitter::class)),
            $this->context(Status::Booting, enableEventEmittingDuringBoot: true),
        );

        self::assertInstanceOf(DefaultEventEmitter::class, $emitter);
    }

    public function testDisableEventEmitting(): void
    {
        $resolver = new EventEmitterResolver($this->createMock(Store::class));

        $emitter = $resolver->resolve(
            new ArgumentMetadata('emitter', Type::object(EventEmitter::class)),
            $this->context(Status::Active, disableEventEmitting: true),
        );

        self::assertInstanceOf(NoopEventEmitter::class, $emitter);
    }

    private function context(
        Status $status,
        bool $enableEventEmittingDuringBoot = false,
        bool $disableEventEmitting = false,
    ): ArgumentResolverContext {
        return new ArgumentResolverContext(
            new Message(new stdClass()),
            new Subscription('foo', status: $status),
            new SubscriberMetadata(
                'foo',
                enableEventEmittingDuringBoot: $enableEventEmittingDuringBoot,
                disableEventEmitting: $disableEventEmitting,
            ),
        );
    }
}
