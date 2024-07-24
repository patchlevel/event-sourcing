<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Snapshot\SnapshotVersionInvalid;
use Patchlevel\EventSourcing\Store\Criteria\CriteriaBuilder;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\Stream;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Store\StreamHeader;
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

    private bool $useStreamHeader;

    /** @param AggregateRootMetadata<T> $metadata */
    public function __construct(
        private Store $store,
        private readonly AggregateRootMetadata $metadata,
        private EventBus|null $eventBus = null,
        private SnapshotStore|null $snapshotStore = null,
        private MessageDecorator|null $messageDecorator = null,
        ClockInterface|null $clock = null,
        LoggerInterface|null $logger = null,
    ) {
        $this->clock = $clock ?? new SystemClock();
        $this->logger = $logger ?? new NullLogger();
        $this->aggregateIsValid = new WeakMap();
        $this->useStreamHeader = $store instanceof StreamDoctrineDbalStore;
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

        if ($this->useStreamHeader) {
            $criteria = (new CriteriaBuilder())
                ->streamName($this->streamName($this->metadata->name, $id->toString()))
                ->archived(false)
                ->build();
        } else {
            $criteria = (new CriteriaBuilder())
                ->aggregateName($this->metadata->name)
                ->aggregateId($id->toString())
                ->archived(false)
                ->build();
        }

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

            if ($this->useStreamHeader) {
                $playhead = $firstMessage->header(StreamHeader::class)->playhead;

                if ($playhead === null) {
                    throw new AggregateNotFound($this->metadata->className, $id);
                }
            } else {
                $playhead = $firstMessage->header(AggregateHeader::class)->playhead;
            }

            $aggregate = $this->metadata->className::createFromEvents(
                $this->unpack($stream),
                $playhead - 1,
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
        if ($this->useStreamHeader) {
            $criteria = (new CriteriaBuilder())
                ->streamName($this->streamName($this->metadata->name, $id->toString()))
                ->build();
        } else {
            $criteria = (new CriteriaBuilder())
                ->aggregateName($this->metadata->name)
                ->aggregateId($id->toString())
                ->build();
        }

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

            $useStreamHeader = $this->useStreamHeader;

            $messages = array_map(
                static function (object $event) use ($aggregateName, $aggregateId, &$playhead, $messageDecorator, $clock, $useStreamHeader) {
                    if ($useStreamHeader) {
                        $header = new StreamHeader(
                            sprintf('%s-%s', $aggregateName, $aggregateId),
                            ++$playhead,
                            $clock->now(),
                        );
                    } else {
                        $header = new AggregateHeader(
                            $aggregateName,
                            $aggregateId,
                            ++$playhead,
                            $clock->now(),
                        );
                    }

                    $message = Message::create($event)->withHeader($header);

                    if ($messageDecorator) {
                        return $messageDecorator($message);
                    }

                    return $message;
                },
                $events,
            );

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

        $this->eventBus?->dispatch(...$messages);
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

        if ($this->useStreamHeader) {
            $criteria = (new CriteriaBuilder())
                ->streamName($this->streamName($this->metadata->name, $id->toString()))
                ->fromPlayhead($aggregate->playhead())
                ->build();
        } else {
            $criteria = (new CriteriaBuilder())
                ->aggregateName($this->metadata->name)
                ->aggregateId($id->toString())
                ->fromPlayhead($aggregate->playhead())
                ->build();
        }

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

    /** @return Traversable<object> */
    private function unpack(Stream $stream): Traversable
    {
        foreach ($stream as $message) {
            yield $message->event();
        }
    }

    private function streamName(string $aggregateName, string $aggregateId): string
    {
        return sprintf('%s-%s', $aggregateName, $aggregateId);
    }
}
