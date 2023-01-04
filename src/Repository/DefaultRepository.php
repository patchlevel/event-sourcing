<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRootInterface;
use Patchlevel\EventSourcing\Aggregate\AggregateRootMetadataAware;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\EventBus\Decorator\MessageDecorator;
use Patchlevel\EventSourcing\EventBus\Decorator\RecordedOnDecorator;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Snapshot\SnapshotVersionInvalid;
use Patchlevel\EventSourcing\Store\ArchivableStore;
use Patchlevel\EventSourcing\Store\Store;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

use function array_map;
use function assert;
use function count;
use function is_a;
use function sprintf;

/**
 * @template T of AggregateRootInterface
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
    private MessageDecorator $messageDecorator;

    /**
     * @param class-string<T> $aggregateClass
     */
    public function __construct(
        Store $store,
        EventBus $eventBus,
        string $aggregateClass,
        ?SnapshotStore $snapshotStore = null,
        ?MessageDecorator $messageDecorator = null,
        ?LoggerInterface $logger = null,
        ?AggregateRootMetadata $metadata = null,
    ) {
        $this->store = $store;
        $this->eventBus = $eventBus;
        $this->aggregateClass = $aggregateClass;
        $this->snapshotStore = $snapshotStore;
        $this->messageDecorator = $messageDecorator ?? new RecordedOnDecorator(new SystemClock());
        $this->logger = $logger ?? new NullLogger();

        if ($metadata) {
            $this->metadata = $metadata;

            return;
        }

        if (!is_a($aggregateClass, AggregateRootMetadataAware::class, true)) {
            throw new RuntimeException();
        }

        $this->metadata = $aggregateClass::metadata();
    }

    /**
     * @return T
     */
    public function load(string $id): AggregateRootInterface
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
            } catch (SnapshotVersionInvalid) {
                $this->logger->debug(
                    sprintf(
                        'snapshot for aggregate "%s" with the id "%s" is invalid',
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
            ),
            $messages[0]->playhead() - 1
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
    public function save(AggregateRootInterface $aggregate): void
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
                count($events)
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
            $events
        );

        $this->store->transactional(function () use ($messages): void {
            $this->store->save(...$messages);
            $this->archive(...$messages);
            $this->eventBus->dispatch(...$messages);
        });
    }

    /**
     * @param class-string<T> $aggregateClass
     *
     * @return T
     */
    private function loadFromSnapshot(string $aggregateClass, string $id): AggregateRootInterface
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
    private function saveSnapshot(AggregateRootInterface $aggregate, array $messages): void
    {
        assert($this->snapshotStore instanceof SnapshotStore);

        $batchSize = $this->metadata->snapshotBatch ?: 1;

        if (count($messages) < $batchSize) {
            return;
        }

        $this->snapshotStore->save($aggregate);
    }

    private function assertRightAggregate(AggregateRootInterface $aggregate): void
    {
        if (!$aggregate instanceof $this->aggregateClass) {
            throw new WrongAggregate($aggregate::class, $this->aggregateClass);
        }
    }

    private function archive(Message ...$messages): void
    {
        if (!$this->store instanceof ArchivableStore) {
            return;
        }

        foreach ($messages as $message) {
            if (!$message->newStreamStart()) {
                continue;
            }

            $this->store->archiveMessages(
                $message->aggregateClass(),
                $message->aggregateId(),
                $message->playhead()
            );
        }
    }
}
