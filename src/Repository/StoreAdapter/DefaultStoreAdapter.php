<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository\StoreAdapter;

use Generator;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamStartHeader;
use Patchlevel\EventSourcing\Store\UniqueConstraintViolation;

/**
 * Stores every aggregate in its own stream. The version is the playhead of the last message,
 * concurrency is guaranteed by the unique constraint on stream and playhead.
 */
final class DefaultStoreAdapter implements StoreAdapter
{
    public function __construct(
        private readonly Store $store,
    ) {
    }

    /**
     * @param AggregateRootMetadata<*> $metadata
     * @param int<0, max>|null         $fromPlayhead
     */
    public function load(AggregateRootMetadata $metadata, string $aggregateId, int|null $fromPlayhead = null): LoadedStream
    {
        $streamName = $metadata->streamName($aggregateId);

        $stream = $this->store->load(
            $fromPlayhead === null
                ? new Criteria(new StreamCriterion($streamName), new ArchivedCriterion(false))
                : new Criteria(new StreamCriterion($streamName), new FromPlayheadCriterion($fromPlayhead)),
        );

        $first = $stream->current();
        $playhead = $first === null ? $fromPlayhead ?? 0 : $first->header(PlayheadHeader::class)->playhead - 1;
        $version = $playhead;

        return new LoadedStream(
            new Stream($this->trackVersion($stream, $version)),
            $playhead,
            static function () use (&$version): Version {
                /** @var int<0, max> $version */
                return new Version($version);
            },
        );
    }

    /** @param AggregateRootMetadata<*> $metadata */
    public function has(AggregateRootMetadata $metadata, string $aggregateId): bool
    {
        return $this->store->count(new Criteria(new StreamCriterion($metadata->streamName($aggregateId)))) > 0;
    }

    /** @param AggregateRootMetadata<*> $metadata */
    public function save(
        AggregateRootMetadata $metadata,
        string $aggregateId,
        Version|null $expectedVersion,
        Message ...$messages,
    ): SaveResult {
        $streamName = $metadata->streamName($aggregateId);
        $playhead = $expectedVersion->value ?? 0;
        $archiveTo = null;
        $stored = [];

        foreach ($messages as $message) {
            $playhead++;

            if ($message->hasHeader(StreamStartHeader::class)) {
                $archiveTo = $playhead;
            }

            $stored[] = $message
                ->withHeader(new StreamNameHeader($streamName))
                ->withHeader(new PlayheadHeader($playhead));
        }

        $messages = $stored;

        try {
            if ($archiveTo === null) {
                $this->store->save(...$messages);
            } else {
                $this->store->transactional(
                    function () use ($messages, $streamName, $archiveTo): void {
                        $this->store->save(...$messages);
                        $this->store->archive(
                            new Criteria(
                                new StreamCriterion($streamName),
                                new ToPlayheadCriterion($archiveTo),
                            ),
                        );
                    },
                );
            }
        } catch (UniqueConstraintViolation $exception) {
            throw new VersionConflict($exception);
        }

        return new SaveResult($messages, new Version($playhead));
    }

    /** @return Generator<int, Message> */
    private function trackVersion(Stream $stream, int &$version): Generator
    {
        try {
            foreach ($stream as $message) {
                $version = $message->header(PlayheadHeader::class)->playhead;

                yield $message;
            }
        } finally {
            $stream->close();
        }
    }
}
