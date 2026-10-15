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
use Patchlevel\EventSourcing\Subscription\Engine\Listener\PartitionCircuitBreaker;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
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

#[CoversClass(PartitionCircuitBreaker::class)]
final class PartitionCircuitBreakerTest extends TestCase
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

        self::assertFalse($this->handle($breaker, $this->partitions[3])->skip, 'not tripped yet');

        $breaker->onHandleMessageError($this->error($this->partitions[2]));

        self::assertTrue($this->handle($breaker, $this->partitions[3])->skip);
        self::assertFalse($this->handle($breaker, new Subscription('other#a', partitionKey: 'a'))->skip, 'other subscribers go on');
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

        self::assertFalse($this->handle($breaker, $this->partitions[5])->skip);
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

    public function testOnlyRunAndBoot(): void
    {
        $breaker = $this->breaker(1);
        $breaker->onCommand(new OnCommand(new Setup()));

        $breaker->onHandleMessageError($this->error($this->partitions[0]));
        $breaker->onResult(new OnResult(new Setup(), new Result()));

        self::assertSame(Status::Active, $this->store->get('profile')->status());
    }

    /** @param positive-int $maxConsecutiveFailures */
    private function breaker(int $maxConsecutiveFailures): PartitionCircuitBreaker
    {
        $subscriber = $maxConsecutiveFailures === 1
            ? new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
            #[Partition(maxConsecutiveFailures: 1)]
            public function partition(Message $message): string
            {
                return 'key';
            }

            #[Subscribe(ProfileVisited::class)]
            public function on(ProfileVisited $event): void
            {
            }
        }
            : new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
            #[Partition(maxConsecutiveFailures: 3)]
            public function partition(Message $message): string
            {
                return 'key';
            }

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

        return new PartitionCircuitBreaker(
            new SubscriptionManager($this->store),
            new MetadataSubscriberAccessorRepository([$subscriber]),
            RetryStrategyRepository::withDefault(new NoRetryStrategy()),
        );
    }

    private function error(Subscription $partition): OnHandleMessageError
    {
        return new OnHandleMessageError($partition, new RuntimeException('database down'), $this->message(), 1);
    }

    private function handle(PartitionCircuitBreaker $breaker, Subscription $partition): OnHandleMessage
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
