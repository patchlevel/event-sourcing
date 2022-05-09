<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Clock;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\MessageDecorator;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\Store;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

use function array_map;
use function assert;
use function count;
use function sprintf;

/**
 * @template T of AggregateRoot
 * @implements Repository<T>
 */
final class DefaultRepository implements Repository
{
    private Store $store;
    private EventBus $eventBus;

    /** @var class-string<T> */
    private string $aggregateClass;

    private ?SnapshotStore $snapshotStore;
    private LoggerInterface $logger;
    private AggregateRootMetadata $metadata;
    private ?MessageDecorator $messageDecorator;

    /**
     * @param class-string<T> $aggregateClass
     */
    public function __construct(
        Store $store,
        EventBus $eventBus,
        string $aggregateClass,
        ?SnapshotStore $snapshotStore = null,
        ?LoggerInterface $logger = null,
        ?MessageDecorator $messageDecorator = null
    ) {
        $this->store = $store;
        $this->eventBus = $eventBus;
        $this->aggregateClass = $aggregateClass;
        $this->snapshotStore = $snapshotStore;
        $this->logger = $logger ?? new NullLogger();
        $this->messageDecorator = $messageDecorator;
        $this->metadata = $aggregateClass::metadata();
    }

    /**
     * @return T
     */
    public function load(string $id): AggregateRoot
    {
        $aggregateClass = $this->aggregateClass;

        if ($this->snapshotStore && $this->metadata->snapshotStore) {
            try {
                return $this->loadFromSnapshot($aggregateClass, $id);
            } catch (SnapshotRebuildFailed $exception) {
                $this->logger->error($exception->getMessage());
            } catch (SnapshotNotFound) {
                $this->logger->debug(
                    sprintf(
                        'snapshot for aggregate "%s" with the id "%s" not found',
                        $aggregateClass,
                        $id
                    )
                );
            }
        }

        $messages = $this->store->load($aggregateClass, $id);

        if (count($messages) === 0) {
            throw new AggregateNotFound($aggregateClass, $id);
        }

        $aggregate = $aggregateClass::createFromEvents(
            array_map(
                static fn (Message $message) => $message->event(),
                $messages
            )
        );

        if ($this->snapshotStore && $this->metadata->snapshotStore) {
            $this->saveSnapshot($aggregate, $messages);
        }

        return $aggregate;
    }

    public function has(string $id): bool
    {
        return $this->store->has($this->aggregateClass, $id);
    }

    /**
     * @param T $aggregate
     */
    public function save(AggregateRoot $aggregate): void
    {
        $this->assertRightAggregate($aggregate);

        $events = $aggregate->releaseEvents();

        if (count($events) === 0) {
            return;
        }

        $messageDecorator = $this->messageDecorator;
        $playhead = $aggregate->playhead() - count($events);

        $messages = array_map(
            static function (object $event) use ($aggregate, &$playhead, $messageDecorator) {
                $message = new Message(
                    $event,
                    [
                        Message::HEADER_AGGREGATE_CLASS => $aggregate::class,
                        Message::HEADER_AGGREGATE_ID => $aggregate->aggregateRootId(),
                        Message::HEADER_PLAYHEAD => ++$playhead,
                        Message::HEADER_RECORDED_ON => Clock::createDateTimeImmutable(),
                    ]
                );

                if ($messageDecorator) {
                    $message = $messageDecorator($message);
                }

                return $message;
            },
            $events
        );

        $this->store->save(...$messages);
        $this->eventBus->dispatch(...$messages);
    }

    /**
     * @param class-string<T> $aggregateClass
     *
     * @return T
     */
    private function loadFromSnapshot(string $aggregateClass, string $id): AggregateRoot
    {
        assert($this->snapshotStore instanceof SnapshotStore);

        $aggregate = $this->snapshotStore->load($aggregateClass, $id);
        $messages = $this->store->load($aggregateClass, $id, $aggregate->playhead());

        if ($messages === []) {
            return $aggregate;
        }

        $events = array_map(
            static fn (Message $message) => $message->event(),
            $messages
        );

        try {
            $aggregate->catchUp($events);
        } catch (Throwable $exception) {
            throw new SnapshotRebuildFailed($aggregateClass, $id, $exception);
        }

        $this->saveSnapshot($aggregate, $messages);

        return $aggregate;
    }

    /**
     * @param T             $aggregate
     * @param list<Message> $messages
     */
    private function saveSnapshot(AggregateRoot $aggregate, array $messages): void
    {
        assert($this->snapshotStore instanceof SnapshotStore);

        $batchSize = $this->metadata->snapshotBatch ?: 1;

        if (count($messages) < $batchSize) {
            return;
        }

        $this->snapshotStore->save($aggregate);
    }

    private function assertRightAggregate(AggregateRoot $aggregate): void
    {
        if (!$aggregate instanceof $this->aggregateClass) {
            throw new WrongAggregate($aggregate::class, $this->aggregateClass);
        }
    }
}
