<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\EventBus\Decorator\MessageDecorator;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Snapshot\SnapshotVersionInvalid;
use Patchlevel\EventSourcing\Store\ArchivableStore;
use Patchlevel\EventSourcing\Store\CriteriaBuilder;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\Stream;
use Patchlevel\EventSourcing\Store\UniqueConstraintViolation;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Traversable;
use WeakMap;

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
    private ClockInterface $clock;
    private LoggerInterface $logger;

    /** @var WeakMap<T, bool> */
    private WeakMap $aggregateIsValid;

    /** @param AggregateRootMetadata<T> $metadata */
    public function __construct(
        private Store $store,
        private EventBus $eventBus,
        private readonly AggregateRootMetadata $metadata,
        private SnapshotStore|null $snapshotStore = null,
        private MessageDecorator|null $messageDecorator = null,
        ClockInterface|null $clock = null,
        LoggerInterface|null $logger = null,
    ) {
        $this->clock = $clock ?? new SystemClock();
        $this->logger = $logger ?? new NullLogger();
        $this->aggregateIsValid = new WeakMap();
    }

    /** @return T */
    public function load(AggregateRootId $id): AggregateRoot
    {
        if ($this->snapshotStore && $this->metadata->snapshot) {
            try {
                $aggregate = $this->loadFromSnapshot($this->metadata->className, $id);

                $this->logger->debug(
                    sprintf(
                        'Repository: Aggregate "%s" with the id "%s" loaded from snapshot.',
                        $this->metadata->name,
                        $id->toString(),
                    ),
                );

                return $aggregate;
            } catch (SnapshotRebuildFailed $exception) {
                $this->logger->error(
                    sprintf(
                        'Repository: Aggregate "%s" with the id "%s" could not be rebuild from snapshot.',
                        $this->metadata->name,
                        $id->toString(),
                    ),
                );

                $this->logger->error($exception->getMessage());
            } catch (SnapshotNotFound) {
                $this->logger->debug(
                    sprintf(
                        'Repository: Snapshot for aggregate "%s" with the id "%s" not found.',
                        $this->metadata->name,
                        $id->toString(),
                    ),
                );
            } catch (SnapshotVersionInvalid) {
                $this->logger->debug(
                    sprintf(
                        'Repository: Snapshot for aggregate "%s" with the id "%s" is invalid.',
                        $this->metadata->name,
                        $id->toString(),
                    ),
                );
            }
        }

        $criteria = (new CriteriaBuilder())
            ->aggregateName($this->metadata->name)
            ->aggregateId($id->toString())
            ->archived(false)
            ->build();

        $stream = null;

        try {
            $stream = $this->store->load($criteria);

            $firstMessage = $stream->current();

            if ($firstMessage === null) {
                $this->logger->debug(
                    sprintf(
                        'Repository: Aggregate "%s" with the id "%s" not found.',
                        $this->metadata->name,
                        $id->toString(),
                    ),
                );

                throw new AggregateNotFound($this->metadata->className, $id);
            }

            $aggregate = $this->metadata->className::createFromEvents(
                $this->unpack($stream),
                $firstMessage->playhead() - 1,
            );

            if ($this->snapshotStore && $this->metadata->snapshot) {
                $this->saveSnapshot($aggregate, $stream->position());
            }
        } finally {
            $stream?->close();
        }

        $this->aggregateIsValid[$aggregate] = true;

        $this->logger->debug(
            sprintf(
                'Repository: Aggregate "%s" with the id "%s" loaded from store.',
                $this->metadata->name,
                $id->toString(),
            ),
        );

        return $aggregate;
    }

    public function has(AggregateRootId $id): bool
    {
        $criteria = (new CriteriaBuilder())
            ->aggregateName($this->metadata->name)
            ->aggregateId($id->toString())
            ->build();

        return $this->store->count($criteria) > 0;
    }

    /** @param T $aggregate */
    public function save(AggregateRoot $aggregate): void
    {
        $this->assertValidAggregate($aggregate);
        $aggregateId = $aggregate->aggregateRootId()->toString();

        try {
            $events = $aggregate->releaseEvents();
            $eventCount = count($events);

            if ($eventCount === 0) {
                return;
            }

            $playhead = $aggregate->playhead() - $eventCount;
            $newAggregate = $playhead === 0;

            if (!isset($this->aggregateIsValid[$aggregate]) && !$newAggregate) {
                $this->logger->error(
                    sprintf(
                        'Repository: Aggregate "%s" with the id "%s" is unknown.',
                        $this->metadata->name,
                        $aggregateId,
                    ),
                );

                throw new AggregateUnknown($aggregate::class, $aggregate->aggregateRootId());
            }

            if ($playhead < 0) {
                $this->logger->error(
                    sprintf(
                        'Repository: Aggregate "%s" with the id "%s" has a playhead mismatch. Expected "%d" but got "%d".',
                        $this->metadata->name,
                        $aggregateId,
                        $aggregate->playhead(),
                        $eventCount,
                    ),
                );

                throw new PlayheadMismatch(
                    $aggregate::class,
                    $aggregate->aggregateRootId(),
                    $aggregate->playhead(),
                    $eventCount,
                );
            }

            $messageDecorator = $this->messageDecorator;
            $clock = $this->clock;

            $aggregateName = $this->metadata->name;

            $messages = array_map(
                static function (object $event) use ($aggregateName, $aggregateId, &$playhead, $messageDecorator, $clock) {
                    $message = Message::create($event)
                        ->withAggregateName($aggregateName)
                        ->withAggregateId($aggregateId)
                        ->withPlayhead(++$playhead)
                        ->withRecordedOn($clock->now());

                    if ($messageDecorator) {
                        return $messageDecorator($message);
                    }

                    return $message;
                },
                $events,
            );

            $this->store->transactional(function () use ($messages, $aggregate, $aggregateId, $newAggregate): void {
                try {
                    $this->store->save(...$messages);
                } catch (UniqueConstraintViolation) {
                    if ($newAggregate) {
                        $this->logger->error(
                            sprintf(
                                'Repository: Aggregate "%s" with the id "%s" already exists.',
                                $aggregate::class,
                                $aggregateId,
                            ),
                        );

                        throw new AggregateAlreadyExists($aggregate::class, $aggregate->aggregateRootId());
                    }

                    $this->logger->error(
                        sprintf(
                            'Repository: Aggregate "%s" with the id "%s" is outdated.',
                            $aggregate::class,
                            $aggregateId,
                        ),
                    );

                    throw new AggregateOutdated($aggregate::class, $aggregate->aggregateRootId());
                }

                $this->archive(...$messages);
                $this->eventBus->dispatch(...$messages);
            });

            $this->aggregateIsValid[$aggregate] = true;

            $this->logger->debug(
                sprintf(
                    'Repository: Aggregate "%s" with the id "%s" saved.',
                    $this->metadata->name,
                    $aggregateId,
                ),
            );
        } catch (Throwable $exception) {
            $this->aggregateIsValid[$aggregate] = false;

            throw $exception;
        }
    }

    /**
     * @param class-string<T> $aggregateClass
     *
     * @return T
     */
    private function loadFromSnapshot(string $aggregateClass, AggregateRootId $id): AggregateRoot
    {
        assert($this->snapshotStore instanceof SnapshotStore);

        $aggregate = $this->snapshotStore->load($aggregateClass, $id);

        $criteria = (new CriteriaBuilder())
            ->aggregateName($this->metadata->name)
            ->aggregateId($id->toString())
            ->fromPlayhead($aggregate->playhead())
            ->build();

        $stream = null;

        try {
            $stream = $this->store->load($criteria);

            if ($stream->current() === null) {
                $this->aggregateIsValid[$aggregate] = true;

                return $aggregate;
            }

            try {
                $aggregate->catchUp($this->unpack($stream));
            } catch (Throwable $exception) {
                throw new SnapshotRebuildFailed($aggregateClass, $id, $exception);
            }

            $this->saveSnapshot($aggregate, $stream->position());
        } finally {
            $stream?->close();
        }

        $this->aggregateIsValid[$aggregate] = true;

        return $aggregate;
    }

    /** @param T $aggregate */
    private function saveSnapshot(AggregateRoot $aggregate, int|null $streamPosition): void
    {
        assert($this->snapshotStore instanceof SnapshotStore);

        if ($streamPosition === null) {
            return;
        }

        $batchSize = (int)$this->metadata->snapshot?->batch ?: 1;
        $count = $streamPosition + 1;

        if ($count < $batchSize) {
            return;
        }

        $this->logger->debug(
            sprintf(
                'Repository: Save snapshot for aggregate "%s" with the id "%s".',
                $this->metadata->className,
                $aggregate->aggregateRootId()->toString(),
            ),
        );

        $this->snapshotStore->save($aggregate);
    }

    private function assertValidAggregate(AggregateRoot $aggregate): void
    {
        if (!$aggregate instanceof $this->metadata->className) {
            throw new WrongAggregate($aggregate::class, $this->metadata->className);
        }

        if (($this->aggregateIsValid[$aggregate] ?? null) === false) {
            throw new AggregateDetached($aggregate::class, $aggregate->aggregateRootId());
        }
    }

    private function archive(Message ...$messages): void
    {
        if (!$this->store instanceof ArchivableStore) {
            return;
        }

        $lastMessageWithNewStreamStart = null;

        foreach ($messages as $message) {
            if (!$message->newStreamStart()) {
                continue;
            }

            $lastMessageWithNewStreamStart = $message;
        }

        if ($lastMessageWithNewStreamStart === null) {
            return;
        }

        $this->store->archiveMessages(
            $lastMessageWithNewStreamStart->aggregateName(),
            $lastMessageWithNewStreamStart->aggregateId(),
            $lastMessageWithNewStreamStart->playhead(),
        );

        $this->logger->debug(
            sprintf(
                'Repository: Archive messages for aggregate "%s" with the id "%s" until playhead "%d".',
                $lastMessageWithNewStreamStart->aggregateName(),
                $lastMessageWithNewStreamStart->aggregateId(),
                $lastMessageWithNewStreamStart->playhead(),
            ),
        );
    }

    /** @return Traversable<object> */
    private function unpack(Stream $stream): Traversable
    {
        foreach ($stream as $message) {
            yield $message->event();
        }
    }
}
