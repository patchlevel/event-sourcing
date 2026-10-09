<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscribeMethodMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\EventFilteredStoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventFilteredStoreMessageLoader::class)]
final class EventFilteredStoreMessageLoaderTest extends TestCase
{
    public function testLoadWithEventFilter(): void
    {
        $stream = new Stream();

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria(
                new FromIndexCriterion(5),
                new EventsCriterion(['profile_created']),
            ))
            ->willReturn($stream);

        $accessor = new MetadataSubscriberAccessor(
            new class {
            },
            new SubscriberMetadata('foo', subscribeMethods: [
                ProfileCreated::class => new SubscribeMethodMetadata('onProfileCreated'),
            ]),
        );

        $subscriberRepository = $this->createMock(SubscriberAccessorRepository::class);
        $subscriberRepository
            ->expects($this->once())
            ->method('get')
            ->with('foo')
            ->willReturn($accessor);

        $loader = new EventFilteredStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            $subscriberRepository,
        );

        self::assertSame($stream, $loader->load(5, [new Subscription('foo')]));
    }

    public function testLoadWithoutFilterBecauseSubscribeAll(): void
    {
        $stream = new Stream();

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria())
            ->willReturn($stream);

        $accessor = new MetadataSubscriberAccessor(
            new class {
            },
            new SubscriberMetadata('foo', subscribeMethods: [
                '*' => new SubscribeMethodMetadata('onAll'),
            ]),
        );

        $subscriberRepository = $this->createMock(SubscriberAccessorRepository::class);
        $subscriberRepository
            ->expects($this->once())
            ->method('get')
            ->with('foo')
            ->willReturn($accessor);

        $loader = new EventFilteredStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            $subscriberRepository,
        );

        self::assertSame($stream, $loader->load(null, [new Subscription('foo')]));
    }

    public function testLoadWithoutFilterBecauseSubscriberNotFound(): void
    {
        $stream = new Stream();

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(new Criteria())
            ->willReturn($stream);

        $subscriberRepository = $this->createMock(SubscriberAccessorRepository::class);
        $subscriberRepository
            ->expects($this->once())
            ->method('get')
            ->with('foo')
            ->willReturn(null);

        $loader = new EventFilteredStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            $subscriberRepository,
        );

        self::assertSame($stream, $loader->load(null, [new Subscription('foo')]));
    }

    public function testLastIndex(): void
    {
        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        $store = $this->createMock(Store::class);
        $store
            ->expects($this->once())
            ->method('load')
            ->with(null, 1, null, true)
            ->willReturn(new Stream([5 => $message]));

        $subscriberRepository = $this->createMock(SubscriberAccessorRepository::class);

        $loader = new EventFilteredStoreMessageLoader(
            $store,
            new AttributeEventMetadataFactory(),
            $subscriberRepository,
        );

        self::assertSame(5, $loader->lastIndex());
    }
}
