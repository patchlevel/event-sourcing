<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Identifier\Identifier;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Repository\MessageDecorator\MessageDecorator;
use Patchlevel\EventSourcing\Repository\StoreAdapter\StoreAdapter;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Snapshot\SnapshotVersionInvalid;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
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
        private readonly StoreAdapter $messageAdapter,
        private readonly AggregateRootMetadata $metadata,
        private readonly EventBus|null $eventBus = null,
        private readonly SnapshotStore|null $snapshotStore = null,
        private readonly MessageDecorator|null $messageDecorator = null,
        ClockInterface|null $clock = null,
        LoggerInterface|null $logger = null,
    ) {
        $this->clock = $clock ?? new SystemClock();
        $this->logger = $logger ?? new NullLogger();
        $this->aggregateIsValid = new WeakMap();
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
            $stream = $this->messageAdapter->load(
                $this->metadata->streamName($id->toString()),
            );

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

            $playhead = $firstMessage->header(PlayheadHeader::class)->playhead;

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

    public function has(Identifier $id): bool
    {
        return $this->messageAdapter->count($this->metadata->streamName($id->toString())) > 0;
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

            $streamName = $this->metadata->streamName($aggregateId);

            $messages = array_map(
                static function (object $event) use (
                    &$playhead,
                    $messageDecorator,
                    $clock,
                ) {
                    $message = Message::create($event)
                        ->withHeader(new PlayheadHeader(++$playhead))
                        ->withHeader(new RecordedOnHeader($clock->now()));

                    if ($messageDecorator) {
                        $message = $messageDecorator($message);
                    }

                    return $message;
                },
                $events,
            );

            try {
                $this->messageAdapter->write($streamName, $messages);
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
    private function loadFromSnapshot(string $aggregateClass, Identifier $id): AggregateRoot
    {
        assert($this->snapshotStore instanceof SnapshotStore);

        $aggregate = $this->snapshotStore->load($aggregateClass, $id);

        $stream = null;

        try {
            $stream = $this->messageAdapter->load($this->metadata->streamName($id->toString()), $aggregate->playhead());

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
}
