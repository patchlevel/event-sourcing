<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Identifier\Identifier;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use Patchlevel\EventSourcing\Repository\StoreAdapter\DefaultStoreAdapter;
use Patchlevel\EventSourcing\Repository\StoreAdapter\StoreAdapter;
use Patchlevel\EventSourcing\Repository\StoreAdapter\Version;
use Patchlevel\EventSourcing\Repository\StoreAdapter\VersionConflict;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Snapshot\SnapshotVersionInvalid;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Store;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Traversable;
use WeakMap;

use function array_map;
use function assert;
use function count;
use function is_a;
use function is_object;
use function sprintf;

/**
 * @template T of AggregateRoot
 * @implements Repository<T>
 */
final class DefaultRepository implements Repository
{
    private StoreAdapter $storeAdapter;
    private ClockInterface $clock;
    private LoggerInterface $logger;

    /** @var WeakMap<T, bool> */
    private WeakMap $aggregateIsValid;

    /** @var WeakMap<T, Version> */
    private WeakMap $versions;

    /** @param AggregateRootMetadata<T> $metadata */
    public function __construct(
        Store|StoreAdapter $store,
        private readonly AggregateRootMetadata $metadata,
        private readonly EventBus|null $eventBus = null,
        private readonly SnapshotStore|null $snapshotStore = null,
        private readonly MessageDecorator|null $messageDecorator = null,
        ClockInterface|null $clock = null,
        LoggerInterface|null $logger = null,
    ) {
        $this->storeAdapter = $store instanceof Store ? new DefaultStoreAdapter($store) : $store;
        $this->clock = $clock ?? new SystemClock();
        $this->logger = $logger ?? new NullLogger();
        $this->aggregateIsValid = new WeakMap();
        $this->versions = new WeakMap();
    }

    /** @return T */
    public function load(Identifier $id): AggregateRoot
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

        $stream = null;

        try {
            $loadedStream = $this->storeAdapter->load($this->metadata, $id->toString());
            $stream = $loadedStream->stream;

            $firstMessage = $stream->current();

            if ($firstMessage === null) {
                if ($this->metadata->autoInitializeMethod) {
                    $aggregate = $this->metadata->className::{$this->metadata->autoInitializeMethod}($id);

                    if (!is_object($aggregate) || !is_a($aggregate, $this->metadata->className, true)) {
                        throw new InvalidAggregate(
                            $this->metadata->autoInitializeMethod,
                            $this->metadata->className,
                            $aggregate,
                        );
                    }

                    $this->logger->debug(
                        sprintf(
                            'Repository: Auto initialize aggregate "%s" with the id "%s".',
                            $this->metadata->name,
                            $id->toString(),
                        ),
                    );

                    $this->aggregateIsValid[$aggregate] = true;
                    $this->versions[$aggregate] = $loadedStream->version();

                    return $aggregate;
                }

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
                $loadedStream->playhead,
            );

            $this->versions[$aggregate] = $loadedStream->version();

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

    public function has(Identifier $id): bool
    {
        return $this->storeAdapter->has($this->metadata, $id->toString());
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

            $messages = array_map(
                static function (object $event) use ($messageDecorator, $clock) {
                    $message = Message::create($event)
                        ->withHeader(new RecordedOnHeader($clock->now()));

                    if ($messageDecorator) {
                        $message = $messageDecorator($message);
                    }

                    return $message;
                },
                $events,
            );

            try {
                $result = $this->storeAdapter->save(
                    $this->metadata,
                    $aggregateId,
                    $this->versions[$aggregate] ?? null,
                    ...$messages,
                );
            } catch (VersionConflict) {
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

            $this->aggregateIsValid[$aggregate] = true;
            $this->versions[$aggregate] = $result->version;

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

        $this->eventBus?->dispatch(...$result->messages);
    }

    /**
     * @param class-string<T> $aggregateClass
     *
     * @return T
     */
    private function loadFromSnapshot(string $aggregateClass, Identifier $id): AggregateRoot
    {
        assert($this->snapshotStore instanceof SnapshotStore);

        $aggregate = $this->snapshotStore->load($aggregateClass, $id);
        $playhead = $aggregate->playhead();
        assert($playhead >= 0);

        $stream = null;

        try {
            $loadedStream = $this->storeAdapter->load(
                $this->metadata,
                $id->toString(),
                $playhead,
            );
            $stream = $loadedStream->stream;

            if ($stream->current() === null) {
                $this->aggregateIsValid[$aggregate] = true;
                $this->versions[$aggregate] = $loadedStream->version();

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
        $this->versions[$aggregate] = $loadedStream->version();

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

    /** @return Traversable<object> */
    private function unpack(Stream $stream): Traversable
    {
        foreach ($stream as $message) {
            yield $message->event();
        }
    }
}
