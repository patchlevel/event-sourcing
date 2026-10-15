<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Attribute\BatchFlush;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnBeforeHandleMessage;
use Patchlevel\EventSourcing\Subscription\Engine\IndexMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\BatchSubscriber;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\MessageProcessor;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionRunner;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchManager;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function range;

#[CoversClass(SubscriptionRunner::class)]
final class SubscriptionRunnerTest extends TestCase
{
    public function testProcessWithoutSubscriptions(): void
    {
        $runner = $this->runner(
            $this->createMock(MessageLoader::class),
            $this->createMock(SubscriberAccessorRepository::class),
        );

        self::assertEquals(ProcessedResult::empty(), $runner->process([], null));
    }

    public function testProcessSkipsUnknownSubscriber(): void
    {
        $loader = $this->createMock(MessageLoader::class);
        $loader
            ->expects($this->never())
            ->method('load');

        $subscriberRepository = $this->createMock(SubscriberAccessorRepository::class);
        $subscriberRepository
            ->expects($this->atLeastOnce())
            ->method('get')
            ->with('foo')
            ->willReturn(null);

        $runner = $this->runner($loader, $subscriberRepository);

        self::assertEquals(
            ProcessedResult::empty(),
            $runner->process([new Subscription('foo')], null),
        );
    }

    public function testProcessMessages(): void
    {
        $subscription = new Subscription('foo', runMode: RunMode::FromBeginning, status: Status::Active);

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        $loader = $this->createMock(MessageLoader::class);
        $loader
            ->expects($this->once())
            ->method('load')
            ->with(null, [$subscription])
            ->willReturn(new Stream([1 => $message, 2 => $message]));

        $subscriberRepository = $this->subscriberRepository();

        $runner = $this->runner($loader, $subscriberRepository);

        $result = $runner->process([$subscription], null);

        self::assertSame(2, $result->processedMessages);
        self::assertTrue($result->finished);
        self::assertSame([], $result->errors);
        self::assertSame(2, $subscription->position());
        self::assertSame(Status::Active, $subscription->status());
    }

    public function testProcessWithLimit(): void
    {
        $subscription = new Subscription('foo', runMode: RunMode::FromBeginning, status: Status::Active);

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        $loader = $this->createMock(MessageLoader::class);
        $loader
            ->expects($this->once())
            ->method('load')
            ->with(null, [$subscription])
            ->willReturn(new Stream([1 => $message, 2 => $message]));

        $runner = $this->runner($loader, $this->subscriberRepository());

        $result = $runner->process([$subscription], 1);

        self::assertSame(1, $result->processedMessages);
        self::assertFalse($result->finished);
        self::assertSame(1, $subscription->position());
    }

    public function testProcessFinishesRunOnceSubscription(): void
    {
        $subscription = new Subscription('foo', runMode: RunMode::Once, status: Status::Active);

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        $loader = $this->createMock(MessageLoader::class);
        $loader
            ->expects($this->once())
            ->method('load')
            ->willReturn(new Stream([1 => $message]));

        $runner = $this->runner($loader, $this->subscriberRepository());

        $result = $runner->process([$subscription], null);

        self::assertTrue($result->finished);
        self::assertSame(Status::Finished, $subscription->status());
    }

    public function testProcessBootFinishesRunOnceSubscription(): void
    {
        $subscription = new Subscription('foo', runMode: RunMode::Once, status: Status::Booting);

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        $loader = $this->createMock(MessageLoader::class);
        $loader
            ->expects($this->once())
            ->method('load')
            ->willReturn(new Stream([1 => $message]));

        $runner = $this->runner($loader, $this->subscriberRepository());

        $runner->process([$subscription], null, true);

        self::assertSame(Status::Finished, $subscription->status());
    }

    public function testProcessBootActivatesSubscription(): void
    {
        $subscription = new Subscription('foo', runMode: RunMode::FromBeginning, status: Status::Booting);

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        $loader = $this->createMock(MessageLoader::class);
        $loader
            ->expects($this->once())
            ->method('load')
            ->willReturn(new Stream([1 => $message]));

        $runner = $this->runner($loader, $this->subscriberRepository());

        $runner->process([$subscription], null, true);

        self::assertSame(Status::Active, $subscription->status());
    }

    public function testPartitionsLoadTheirInboxesInChunks(): void
    {
        $main = new Subscription('foo', status: Status::Active, position: 3000);
        $partitions = [
            Subscription::partition($main, 'a', 0),
            Subscription::partition($main, 'b', 0),
            Subscription::partition($main, 'c', 0),
        ];

        $store = new InMemorySubscriptionStore([$main, ...$partitions]);

        foreach ($partitions as $offset => $partition) {
            $store->addToInbox([$partition->id() => range($offset + 1, 3000, 3)]);
        }

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        /** @var list<list<int>> $loadedChunks */
        $loadedChunks = [];

        $loader = $this->createMock(IndexMessageLoader::class);
        $loader
            ->expects($this->exactly(3))
            ->method('loadIndices')
            ->willReturnCallback(static function (array $indices) use ($message, &$loadedChunks): Stream {
                $loadedChunks[] = $indices;
                $messages = [];

                foreach ($indices as $index) {
                    self::assertIsInt($index);
                    $messages[$index] = $message;
                }

                return new Stream($messages);
            });

        $runner = $this->runner($loader, $this->subscriberRepository(), $store);

        $result = $runner->process($partitions, null);

        self::assertSame([], $result->errors);
        self::assertSame(3000, $result->processedMessages);
        self::assertSame([range(1, 1000), range(1001, 2000), range(2001, 3000)], $loadedChunks);
        self::assertSame(2998, $partitions[0]->position());
        self::assertSame(2999, $partitions[1]->position());
        self::assertSame(3000, $partitions[2]->position());
    }

    public function testStoppedSubscriptionDoesNotProcessTheFollowingMessages(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            public int $handled = 0;

            #[Subscribe(ProfileVisited::class)]
            public function on(ProfileVisited $event): void
            {
                $this->handled++;
            }
        };

        $result = $this->processWithStopAtTheSecondMessage($subscriber, $subscription = new Subscription('foo', status: Status::Active, position: 0));

        self::assertSame([], $result->errors);
        self::assertSame(1, $subscriber->handled, 'the message after the stopped one is not processed either');
        self::assertSame(1, $subscription->position());
    }

    public function testStoppedBatchOnlyMovesToTheLastHandledMessage(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            public int $flushed = 0;

            #[Subscribe(ProfileVisited::class)]
            public function on(ProfileVisited $event): void
            {
            }

            #[BatchFlush]
            public function flush(): void
            {
                $this->flushed++;
            }
        };

        $result = $this->processWithStopAtTheSecondMessage($subscriber, $subscription = new Subscription('foo', status: Status::Active, position: 0));

        self::assertSame([], $result->errors);
        self::assertSame(1, $subscriber->flushed);
        self::assertSame(1, $subscription->position(), 'the flush does not move the position past the stopped message');
    }

    private function processWithStopAtTheSecondMessage(object $subscriber, Subscription $subscription): ProcessedResult
    {
        $subscriberRepository = new MetadataSubscriberAccessorRepository([$subscriber]);

        $handled = 0;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(OnBeforeHandleMessage::class, static function (OnBeforeHandleMessage $event) use (&$handled): void {
            $handled++;

            if ($handled !== 2) {
                return;
            }

            $event->stop = true;
        }, 64);
        $eventDispatcher->addSubscriber(new BatchSubscriber(new BatchManager(), $subscriberRepository));

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        $loader = $this->createMock(MessageLoader::class);
        $loader
            ->method('load')
            ->willReturn(new Stream([1 => $message, 2 => $message, 3 => $message]));

        $runner = $this->runner($loader, $subscriberRepository, null, $eventDispatcher);

        return $runner->process([$subscription], null);
    }

    private function runner(
        MessageLoader $loader,
        SubscriberAccessorRepository $subscriberRepository,
        SubscriptionStore|null $store = null,
        EventDispatcher $eventDispatcher = new EventDispatcher(),
    ): SubscriptionRunner {
        return new SubscriptionRunner(
            $loader,
            new SubscriptionManager($store ?? $this->createMock(SubscriptionStore::class)),
            $subscriberRepository,
            new MessageProcessor($subscriberRepository, $eventDispatcher),
            $eventDispatcher,
        );
    }

    private function subscriberRepository(): SubscriberAccessorRepository
    {
        $accessor = new MetadataSubscriberAccessor(
            new class {
            },
            new SubscriberMetadata('foo'),
        );

        $subscriberRepository = $this->createMock(SubscriberAccessorRepository::class);
        $subscriberRepository
            ->method('get')
            ->with('foo')
            ->willReturn($accessor);

        return $subscriberRepository;
    }
}
