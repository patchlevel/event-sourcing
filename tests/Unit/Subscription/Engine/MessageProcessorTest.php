<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Attribute\Partition;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Engine\InvalidPartitionKey;
use Patchlevel\EventSourcing\Subscription\Engine\MessageProcessor;
use Patchlevel\EventSourcing\Subscription\Engine\PartitionShardsChanged;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscriber\NoSuitableResolver;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionType;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function crc32;
use function str_repeat;

#[CoversClass(MessageProcessor::class)]
final class MessageProcessorTest extends TestCase
{
    public function testProcessInvokesSubscribeMethod(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public ProfileVisited|null $event = null;

            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(ProfileVisited $event): void
            {
                $this->event = $event;
            }
        };

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new SubscriptionManager(new InMemorySubscriptionStore()),
            new EventDispatcher(),
            [],
            new NullLogger(),
        );

        $event = new ProfileVisited(ProfileId::fromString('1'));
        $message = Message::create($event);
        $subscription = new Subscription('test', status: Status::Active);

        $error = $processor->process(1, $message, $subscription);

        self::assertNull($error);
        self::assertSame($event, $subscriber->event);
        self::assertSame(1, $subscription->position());
    }

    public function testProcessResolvesMessageArgument(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(Message $message): void
            {
                $this->message = $message;
            }
        };

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new SubscriptionManager(new InMemorySubscriptionStore()),
            new EventDispatcher(),
            [],
            new NullLogger(),
        );

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));
        $subscription = new Subscription('test', status: Status::Active);

        $error = $processor->process(1, $message, $subscription);

        self::assertNull($error);
        self::assertSame($message, $subscriber->message);
    }

    public function testProcessResolvesMultipleArguments(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public ProfileVisited|null $event = null;
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(ProfileVisited $event, Message $message): void
            {
                $this->event = $event;
                $this->message = $message;
            }
        };

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new SubscriptionManager(new InMemorySubscriptionStore()),
            new EventDispatcher(),
            [],
            new NullLogger(),
        );

        $event = new ProfileVisited(ProfileId::fromString('1'));
        $message = Message::create($event);
        $subscription = new Subscription('test', status: Status::Active);

        $error = $processor->process(1, $message, $subscription);

        self::assertNull($error);
        self::assertSame($event, $subscriber->event);
        self::assertSame($message, $subscriber->message);
    }

    public function testProcessReturnsErrorWhenArgumentCannotBeResolved(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(ProfileVisited $event, int $unresolved): void
            {
            }
        };

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new SubscriptionManager(new InMemorySubscriptionStore()),
            new EventDispatcher(),
            [],
            new NullLogger(),
        );

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));
        $subscription = new Subscription('test', status: Status::Active);

        $error = $processor->process(1, $message, $subscription);

        self::assertNotNull($error);
        self::assertInstanceOf(NoSuitableResolver::class, $error->throwable);
    }

    public function testMainSubscriptionOfPartitionedSubscriberCreatesPartitions(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public int $calls = 0;

            #[Partition(shards: 4)]
            public function partition(ProfileVisited $event): string
            {
                return 'p-' . $event->visitorId->toString();
            }

            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(ProfileVisited $event): void
            {
                $this->calls++;
            }
        };

        $store = new InMemorySubscriptionStore();
        $subscriptionManager = new SubscriptionManager($store);

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            $subscriptionManager,
            new EventDispatcher(),
        );

        $root = new Subscription('test', status: Status::Active);

        self::assertNull($processor->process(1, Message::create(new ProfileVisited(ProfileId::fromString('1'))), $root));
        self::assertNull($processor->process(2, Message::create(new ProfileVisited(ProfileId::fromString('2'))), $root));
        self::assertNull($processor->process(3, Message::create(new ProfileVisited(ProfileId::fromString('1'))), $root));

        $subscriptionManager->flush();

        self::assertSame(0, $subscriber->calls, 'the main subscription never calls the subscriber');
        self::assertSame(3, $root->position());
        self::assertTrue($processor->hasDiscoveredPartitions($root));

        $partitions = $store->find(new SubscriptionCriteria(types: [SubscriptionType::Partition]));

        self::assertCount(2, $partitions);
        self::assertSame('test#p-1', $partitions[0]->id());
        self::assertSame(0, $partitions[0]->position());
        self::assertSame(crc32('p-1') % 4, $partitions[0]->shard());
        self::assertSame('test#p-2', $partitions[1]->id());
        self::assertSame(1, $partitions[1]->position());
    }

    public function testPartitionSkipsForeignMessages(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public int $calls = 0;

            #[Partition]
            public function partition(ProfileVisited $event): string
            {
                return 'p-' . $event->visitorId->toString();
            }

            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(ProfileVisited $event): void
            {
                $this->calls++;
            }
        };

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new SubscriptionManager(new InMemorySubscriptionStore()),
            new EventDispatcher(),
        );

        $root = new Subscription('test', status: Status::Active);
        $partition = Subscription::partition($root, 'p-1', null, 0);

        self::assertNull($processor->process(1, Message::create(new ProfileVisited(ProfileId::fromString('1'))), $partition));
        self::assertNull($processor->process(2, Message::create(new ProfileVisited(ProfileId::fromString('2'))), $partition));

        self::assertSame(1, $subscriber->calls);
        self::assertSame(2, $partition->position());
    }

    public function testPartitionKeyWithSeparator(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            #[Partition]
            public function partition(): string
            {
                return 'a#b';
            }

            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(): void
            {
            }
        };

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new SubscriptionManager(new InMemorySubscriptionStore()),
            new EventDispatcher(),
        );

        $root = new Subscription('test', status: Status::Active);

        $error = $processor->process(1, Message::create(new ProfileVisited(ProfileId::fromString('1'))), $root);

        self::assertNotNull($error);
        self::assertInstanceOf(InvalidPartitionKey::class, $error->throwable);
        self::assertNull($root->position());
    }

    public function testPartitionKeyTooLong(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            #[Partition]
            public function partition(): string
            {
                // "test#" + 251 characters = 256 bytes
                return str_repeat('a', 251);
            }

            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(): void
            {
            }
        };

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new SubscriptionManager(new InMemorySubscriptionStore()),
            new EventDispatcher(),
        );

        $root = new Subscription('test', status: Status::Active);

        $error = $processor->process(1, Message::create(new ProfileVisited(ProfileId::fromString('1'))), $root);

        self::assertNotNull($error);
        self::assertInstanceOf(InvalidPartitionKey::class, $error->throwable);
        self::assertStringContainsString('too long', $error->message);
        self::assertNull($root->position());
    }

    public function testPartitionKeyWithMaxLength(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            #[Partition]
            public function partition(): string
            {
                // "test#" + 250 characters = 255 bytes
                return str_repeat('a', 250);
            }

            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(): void
            {
            }
        };

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new SubscriptionManager(new InMemorySubscriptionStore()),
            new EventDispatcher(),
        );

        $root = new Subscription('test', status: Status::Active);

        self::assertNull($processor->process(1, Message::create(new ProfileVisited(ProfileId::fromString('1'))), $root));
        self::assertSame(1, $root->position());
    }

    public function testPartitionWithChangedShards(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            #[Partition(shards: 2)]
            public function partition(): string
            {
                return 'a';
            }

            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(): void
            {
            }
        };

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new SubscriptionManager(new InMemorySubscriptionStore()),
            new EventDispatcher(),
        );

        $root = new Subscription('test', status: Status::Active);
        $partition = Subscription::partition($root, 'a', 7, 0);

        $error = $processor->process(1, Message::create(new ProfileVisited(ProfileId::fromString('1'))), $partition);

        self::assertNotNull($error);
        self::assertInstanceOf(PartitionShardsChanged::class, $error->throwable);
    }
}
