<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\EventBus\Decorator\MessageDecorator;
use Patchlevel\EventSourcing\EventBus\Decorator\RecordedOnDecorator;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Snapshot\SnapshotVersionInvalid;
use Patchlevel\EventSourcing\Store\SplitEventstreamStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\TransactionStore;
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
    private LoggerInterface $logger;
    private AggregateRootMetadata $metadata;
    private MessageDecorator $messageDecorator;

    /** @param class-string<T> $aggregateClass */
    public function __construct(
        private Store $store,
        private EventBus $eventBus,
        private string $aggregateClass,
        private SnapshotStore|null $snapshotStore = null,
        MessageDecorator|null $messageDecorator = null,
        LoggerInterface|null $logger = null,
    ) {
        $this->messageDecorator = $messageDecorator ?? new RecordedOnDecorator(new SystemClock());
        $this->logger = $logger ?? new NullLogger();
        $this->metadata = $aggregateClass::metadata();
    }

    /** @return T */
    public function load(string $id): AggregateRoot
    {
        $aggregateClass = $this->aggregateClass;

        if ($this->snapshotStore && $this->metadata->snapshotStore !== null) {
            try {
                return $this->loadFromSnapshot($aggregateClass, $id);
            } catch (SnapshotRebuildFailed $exception) {
                $this->logger->error($exception->getMessage());
            } catch (SnapshotNotFound) {
                $this->logger->debug(
                    sprintf(
                        'snapshot for aggregate "%s" with the id "%s" not found',
                        $aggregateClass,
                        $id,
                    ),
                );
            } catch (SnapshotVersionInvalid) {
                $this->logger->debug(
                    sprintf(
                        'snapshot for aggregate "%s" with the id "%s" is invalid',
                        $aggregateClass,
                        $id,
                    ),
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
                $messages,
            ),
            $messages[0]->playhead() - 1,
        );

        if ($this->snapshotStore && $this->metadata->snapshotStore !== null) {
            $this->saveSnapshot($aggregate, $messages);
        }

        return $aggregate;
    }

    public function has(string $id): bool
    {
        return $this->store->has($this->aggregateClass, $id);
    }

    /** @param T $aggregate */
    public function save(AggregateRoot $aggregate): void
    {
        $this->assertRightAggregate($aggregate);

        $events = $aggregate->releaseEvents();

        if (count($events) === 0) {
            return;
        }

        $messageDecorator = $this->messageDecorator;
        $playhead = $aggregate->playhead() - count($events);

        if ($playhead < 0) {
            throw new PlayheadMismatch(
                $aggregate::class,
                $aggregate->aggregateRootId(),
                $aggregate->playhead(),
                count($events),
            );
        }

        $messages = array_map(
            static function (object $event) use ($aggregate, &$playhead, $messageDecorator) {
                $message = Message::create($event)
                    ->withAggregateClass($aggregate::class)
                    ->withAggregateId($aggregate->aggregateRootId())
                    ->withPlayhead(++$playhead);

                return $messageDecorator($message);
            },
            $events,
        );

        if ($this->store instanceof TransactionStore) {
            $this->store->transactional(function () use ($messages): void {
                $this->store->save(...$messages);

                if ($this->store instanceof SplitEventstreamStore) {
                    foreach ($messages as $message) {
                        if (!$message->newStreamStart()) {
                            continue;
                        }

                        $this->store->archiveMessages(
                            $message->aggregateClass(),
                            $message->aggregateId(),
                            $message->playhead(),
                        );
                    }
                }

                $this->eventBus->dispatch(...$messages);
            });

            return;
        }

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
            $messages,
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

        $batchSize = (int)$this->metadata->snapshotBatch ?: 1;

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
