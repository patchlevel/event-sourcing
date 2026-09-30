<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadata;
use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriberEventFilter;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\BatchingSubscriber;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriberEventFilter::class)]
final class SubscriberEventFilterTest extends TestCase
{
    public function testNoSubscriptions(): void
    {
        $filter = new SubscriberEventFilter(
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([]),
        );

        self::assertSame([], $filter->events([]));
    }

    public function testEventNamesOfSubscriber(): void
    {
        $filter = new SubscriberEventFilter(
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber()]),
        );

        self::assertSame(
            ['profile_visited'],
            $filter->events([new Subscription(BatchingSubscriber::ID)]),
        );
    }

    public function testEventNamesOfMultipleSubscribers(): void
    {
        $subscriber = new #[Subscriber('other', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileCreated::class)]
            public function handle(Message $message): void
            {
            }
        };

        $filter = new SubscriberEventFilter(
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber(), $subscriber]),
        );

        self::assertSame(
            ['profile_visited', 'profile_created'],
            $filter->events([new Subscription(BatchingSubscriber::ID), new Subscription('other')]),
        );
    }

    public function testAliasesAreAddedAndDuplicatesRemoved(): void
    {
        $eventMetadataFactory = $this->createMock(EventMetadataFactory::class);
        $eventMetadataFactory
            ->expects($this->exactly(2))
            ->method('metadata')
            ->with(ProfileVisited::class)
            ->willReturn(new EventMetadata('profile_visited', aliases: ['old_profile_visited']));

        $subscriber = new #[Subscriber('other', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
            }
        };

        $filter = new SubscriberEventFilter(
            $eventMetadataFactory,
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber(), $subscriber]),
        );

        self::assertSame(
            ['profile_visited', 'old_profile_visited'],
            $filter->events([new Subscription(BatchingSubscriber::ID), new Subscription('other')]),
        );
    }

    public function testCatchAllSubscriberDisablesFilter(): void
    {
        $eventMetadataFactory = $this->createMock(EventMetadataFactory::class);
        $eventMetadataFactory
            ->expects($this->never())
            ->method('metadata');

        $subscriber = new #[Subscriber('catch_all', RunMode::FromBeginning)]
        class {
            #[Subscribe(Subscribe::ALL)]
            public function handle(Message $message): void
            {
            }
        };

        $filter = new SubscriberEventFilter(
            $eventMetadataFactory,
            new MetadataSubscriberAccessorRepository([$subscriber, new BatchingSubscriber()]),
        );

        self::assertSame(
            [],
            $filter->events([new Subscription('catch_all'), new Subscription(BatchingSubscriber::ID)]),
        );
    }

    public function testCatchAllSubscriberAfterOtherSubscriberDisablesFilter(): void
    {
        $subscriber = new #[Subscriber('catch_all', RunMode::FromBeginning)]
        class {
            #[Subscribe(Subscribe::ALL)]
            public function handle(Message $message): void
            {
            }
        };

        $filter = new SubscriberEventFilter(
            new AttributeEventMetadataFactory(),
            new MetadataSubscriberAccessorRepository([new BatchingSubscriber(), $subscriber]),
        );

        self::assertSame(
            [],
            $filter->events([new Subscription(BatchingSubscriber::ID), new Subscription('catch_all')]),
        );
    }

    public function testSubscriberWithoutMetadataDisablesFilter(): void
    {
        $eventMetadataFactory = $this->createMock(EventMetadataFactory::class);
        $eventMetadataFactory
            ->expects($this->never())
            ->method('metadata');

        $subscriberRepository = $this->createMock(SubscriberAccessorRepository::class);
        $subscriberRepository
            ->expects($this->once())
            ->method('get')
            ->with('custom')
            ->willReturn($this->createMock(SubscriberAccessor::class));

        $filter = new SubscriberEventFilter(
            $eventMetadataFactory,
            $subscriberRepository,
        );

        self::assertSame([], $filter->events([new Subscription('custom')]));
    }
}
