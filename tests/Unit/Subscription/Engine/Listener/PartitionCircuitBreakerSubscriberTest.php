<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Attribute\Partition;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessage;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageError;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageSuccess;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnResult;
use Patchlevel\EventSourcing\Subscription\Engine\FailedMessageProcessor;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\PartitionCircuitBreakerSubscriber;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Partitioner\StreamPartitioner;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\NoRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_map;
use function range;

#[CoversClass(PartitionCircuitBreakerSubscriber::class)]
final class PartitionCircuitBreakerSubscriberTest extends TestCase
{
    private InMemorySubscriptionStore $store;

    /** @var list<Subscription> */
    private array $partitions;

    public function testTripsAfterTheThresholdAndSkipsTheOtherPartitions(): void
    {
        $breaker = $this->breaker(3);
        $breaker->onCommand(new OnCommand(new Run()));

        foreach ([0, 1] as $i) {
            $breaker->onHandleMessageError($this->error($this->partitions[$i]));
        }

        self::assertFalse($this->handle($breaker, $this->partitions[3])->stop, 'not tripped yet');

        $breaker->onHandleMessageError($this->error($this->partitions[2]));

        self::assertTrue($this->handle($breaker, $this->partitions[3])->stop);
        self::assertFalse($this->handle($breaker, new Subscription('other#a', partitionKey: 'a'))->stop, 'other subscribers go on');
    }

    public function testSuccessResetsTheFailuresInARow(): void
    {
        $breaker = $this->breaker(3);
        $breaker->onCommand(new OnCommand(new Run()));

        $breaker->onHandleMessageError($this->error($this->partitions[0]));
        $breaker->onHandleMessageError($this->error($this->partitions[1]));
        $breaker->onHandleMessageSuccess(new OnHandleMessageSuccess($this->partitions[2], $this->message(), 1));
        $breaker->onHandleMessageError($this->error($this->partitions[3]));
        $breaker->onHandleMessageError($this->error($this->partitions[4]));

        self::assertFalse($this->handle($breaker, $this->partitions[5])->stop);
    }

    public function testStopsTheMainSubscriptionAndSetsBackThePartitions(): void
    {
        $breaker = $this->breaker(3);
        $breaker->onCommand(new OnCommand(new Run()));

        foreach ([0, 1, 2] as $i) {
            $this->partitions[$i]->error(new RuntimeException('database down'));
            $this->store->update($this->partitions[$i]);
            $breaker->onHandleMessageError($this->error($this->partitions[$i]));
        }

        $breaker->onResult(new OnResult(new Run(), new Result()));

        $main = $this->store->get('profile');
        self::assertSame(Status::Failed, $main->status(), 'without retries left the main subscription fails right away');
        self::assertSame('database down', $main->subscriptionError()?->errorMessage);

        foreach ($this->partitions as $partition) {
            self::assertSame(Status::Active, $this->store->get($partition->id())->status(), $partition->id());
            self::assertNull($this->store->get($partition->id())->subscriptionError());
        }
    }

    public function testFailedGroupInTheResultTripsTheBreaker(): void
    {
        $breaker = $this->breaker(3);
        $breaker->onCommand(new OnCommand(new Run()));

        $errors = [];

        foreach ([0, 1, 2, 3] as $i) {
            $this->partitions[$i]->error(new RuntimeException('connection lost'));
            $this->store->update($this->partitions[$i]);
            $errors[] = new Error($this->partitions[$i]->id(), 'connection lost', new RuntimeException('connection lost'));
        }

        $breaker->onResult(new OnResult(new Run(), new Result($errors)));

        self::assertNotSame(Status::Active, $this->store->get('profile')->status());

        foreach ($this->partitions as $partition) {
            self::assertSame(Status::Active, $this->store->get($partition->id())->status(), $partition->id());
        }
    }

    public function testPartitionsStayInErrorIfTheMainSubscriptionCanNotBeStopped(): void
    {
        $breaker = $this->breaker(3);
        $breaker->onCommand(new OnCommand(new Run()));

        // e.g. claimed by another worker, here simply no longer in a status the breaker stops
        $main = $this->store->get('profile');
        $main->pause();
        $this->store->update($main);

        foreach ([0, 1, 2] as $i) {
            $this->partitions[$i]->error(new RuntimeException('database down'));
            $this->store->update($this->partitions[$i]);
            $breaker->onHandleMessageError($this->error($this->partitions[$i]));
        }

        $breaker->onResult(new OnResult(new Run(), new Result()));

        self::assertSame(Status::Paused, $this->store->get('profile')->status());

        foreach ([0, 1, 2] as $i) {
            self::assertSame(Status::Error, $this->store->get($this->partitions[$i]->id())->status(), 'no restart without backoff');
        }
    }

    public function testPartitionsAreSetBackIfTheMainSubscriptionIsAlreadyStopped(): void
    {
        $breaker = $this->breaker(3);
        $breaker->onCommand(new OnCommand(new Run()));

        $main = $this->store->get('profile');
        $main->error(new RuntimeException('stopped by another worker'));
        $this->store->update($main);

        foreach ([0, 1, 2] as $i) {
            $this->partitions[$i]->error(new RuntimeException('database down'));
            $this->store->update($this->partitions[$i]);
            $breaker->onHandleMessageError($this->error($this->partitions[$i]));
        }

        $breaker->onResult(new OnResult(new Run(), new Result()));

        foreach ([0, 1, 2] as $i) {
            self::assertSame(Status::Active, $this->store->get($this->partitions[$i]->id())->status());
        }
    }

    public function testHeldBackFailureIsDroppedIfTheBreakerTrips(): void
    {
        $breaker = $this->breaker(3);
        $breaker->onCommand(new OnCommand(new Run()));

        foreach ([0, 1, 2] as $i) {
            $event = $this->error($this->partitions[$i]);
            $event->transitionToFailed = true;

            $breaker->onHandleMessageError($event);
            $breaker->holdBackFailure($event);

            self::assertFalse($event->transitionToFailed, 'the fail subscriber does not see it');
            self::assertSame(Status::Active, $this->partitions[$i]->status(), 'nothing about the failure is stored yet');
        }

        $breaker->onResult(new OnResult(new Run(), new Result()));

        foreach ([0, 1, 2] as $i) {
            self::assertSame(Status::Active, $this->store->get($this->partitions[$i]->id())->status(), 'set back, not failed');
        }
    }

    public function testHeldBackFailureFailsThePartitionWithoutTrip(): void
    {
        $breaker = $this->breaker(3);
        $breaker->onCommand(new OnCommand(new Run()));

        $event = $this->error($this->partitions[0]);
        $event->transitionToFailed = true;

        $breaker->onHandleMessageError($event);
        $breaker->holdBackFailure($event);
        $this->store->update($this->partitions[0]);

        $breaker->onResult(new OnResult(new Run(), new Result()));

        self::assertSame(Status::Active, $this->store->get('profile')->status());
        self::assertSame(Status::Failed, $this->store->get($this->partitions[0]->id())->status());
    }

    /** If the run does not get to its end, the partition is still active and processes the message again. */
    public function testHeldBackFailureSurvivesACrash(): void
    {
        $breaker = $this->breaker(3);
        $breaker->onCommand(new OnCommand(new Run()));

        $event = $this->error($this->partitions[0]);
        $event->transitionToFailed = true;

        $breaker->onHandleMessageError($event);
        $breaker->holdBackFailure($event);
        $this->store->update($this->partitions[0]);

        // the process dies before the result, the next command starts fresh
        $breaker->onCommand(new OnCommand(new Run()));
        $breaker->onResult(new OnResult(new Run(), new Result()));

        $partition = $this->store->get($this->partitions[0]->id());
        self::assertSame(Status::Active, $partition->status());
        self::assertSame(0, $partition->position(), 'the message is processed again');
    }

    public function testHeldBackFailureOfAMessageProcessedMeanwhile(): void
    {
        $breaker = $this->breaker(3);
        $breaker->onCommand(new OnCommand(new Run()));

        $event = $this->error($this->partitions[0]);
        $event->transitionToFailed = true;

        $breaker->onHandleMessageError($event);
        $breaker->holdBackFailure($event);

        // another worker processed the message in the meantime
        $partition = $this->store->get($this->partitions[0]->id());
        $partition->changePosition(1);
        $this->store->update($partition);

        $breaker->onResult(new OnResult(new Run(), new Result()));

        self::assertSame(Status::Active, $this->store->get($this->partitions[0]->id())->status());
    }

    /** A main subscription that is not running can not be stopped, the held back failures are dropped anyway. */
    public function testHeldBackFailureIsDroppedIfTheMainSubscriptionCanNotBeStopped(): void
    {
        $breaker = $this->breaker(3);
        $breaker->onCommand(new OnCommand(new Run()));

        $main = $this->store->get('profile');
        $main->pause();
        $this->store->update($main);

        foreach ([0, 1, 2] as $i) {
            $event = $this->error($this->partitions[$i]);
            $event->transitionToFailed = true;

            $breaker->onHandleMessageError($event);
            $breaker->holdBackFailure($event);
            $this->store->update($this->partitions[$i]);
        }

        $breaker->onResult(new OnResult(new Run(), new Result()));

        self::assertSame(Status::Paused, $this->store->get('profile')->status());

        foreach ([0, 1, 2] as $i) {
            $partition = $this->store->get($this->partitions[$i]->id());

            self::assertSame(Status::Active, $partition->status(), 'not failed during a systemic failure');
            self::assertSame(0, $partition->position(), 'the message is not skipped');
        }
    }

    public function testOnlyRunAndBoot(): void
    {
        $breaker = $this->breaker(1);
        $breaker->onCommand(new OnCommand(new Setup()));

        $breaker->onHandleMessageError($this->error($this->partitions[0]));
        $breaker->onResult(new OnResult(new Setup(), new Result()));

        self::assertSame(Status::Active, $this->store->get('profile')->status());
    }

    /** @param positive-int $maxConsecutiveFailures */
    private function breaker(int $maxConsecutiveFailures): PartitionCircuitBreakerSubscriber
    {
        $subscriber = $maxConsecutiveFailures === 1
            ? new #[Subscriber('profile', RunMode::FromBeginning)]
        #[Partition(new StreamPartitioner(), maxConsecutiveFailures: 1)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function on(ProfileVisited $event): void
            {
            }
        }
            : new #[Subscriber('profile', RunMode::FromBeginning)]
        #[Partition(new StreamPartitioner(), maxConsecutiveFailures: 3)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function on(ProfileVisited $event): void
            {
            }
        };

        $main = new Subscription('profile', status: Status::Active, position: 10);
        $this->partitions = array_map(
            static fn (int $i): Subscription => Subscription::partition($main, (string)$i, 0),
            range(0, 5),
        );

        $this->store = new InMemorySubscriptionStore([$main, ...$this->partitions]);

        $subscriptionManager = new SubscriptionManager($this->store);
        $subscriberRepository = new MetadataSubscriberAccessorRepository([$subscriber]);

        return new PartitionCircuitBreakerSubscriber(
            $subscriptionManager,
            $subscriberRepository,
            RetryStrategyRepository::withDefault(new NoRetryStrategy()),
            new FailedMessageProcessor($subscriptionManager, $subscriberRepository),
        );
    }

    private function error(Subscription $partition): OnHandleMessageError
    {
        return new OnHandleMessageError($partition, new RuntimeException('database down'), $this->message(), 1);
    }

    private function handle(PartitionCircuitBreakerSubscriber $breaker, Subscription $partition): OnHandleMessage
    {
        $event = new OnHandleMessage($partition, $this->message());
        $breaker->onHandleMessage($event);

        return $event;
    }

    private function message(): Message
    {
        return Message::create(new ProfileVisited(ProfileId::fromString('1')));
    }
}
