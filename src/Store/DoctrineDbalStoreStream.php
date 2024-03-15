<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use Generator;
use IteratorAggregate;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Traversable;

/** @implements IteratorAggregate<Message> */
final class DoctrineDbalStoreStream implements Stream, IteratorAggregate
{
    private Result|null $result;

    /** @var Generator<Message> */
    private Generator|null $generator;

    /** @var positive-int|0|null */
    private int|null $position;

    /** @var positive-int|null */
    private int|null $index;

    public function __construct(
        Result $result,
        EventSerializer $eventSerializer,
        HeadersSerializer $headersSerializer,
        AbstractPlatform $platform,
    ) {
        $this->result = $result;
        $this->generator = $this->buildGenerator($result, $eventSerializer, $headersSerializer, $platform);
        $this->position = null;
        $this->index = null;
    }

    public function close(): void
    {
        $this->result?->free();

        $this->result = null;
        $this->generator = null;
    }

    public function next(): void
    {
        if ($this->result === null || $this->generator === null) {
            throw new StreamClosed();
        }

        $this->generator->next();
    }

    public function end(): bool
    {
        if ($this->result === null || $this->generator === null) {
            throw new StreamClosed();
        }

        return !$this->generator->valid();
    }

    public function current(): Message|null
    {
        if ($this->result === null || $this->generator === null) {
            throw new StreamClosed();
        }

        return $this->generator->current() ?: null;
    }

    /** @return positive-int|0|null */
    public function position(): int|null
    {
        if ($this->result === null || $this->generator === null) {
            throw new StreamClosed();
        }

        if ($this->position === null) {
            $this->generator->key();
        }

        return $this->position;
    }

    /** @return positive-int|null */
    public function index(): int|null
    {
        if ($this->result === null || $this->generator === null) {
            throw new StreamClosed();
        }

        if ($this->index === null) {
            $this->generator->key();
        }

        return $this->index;
    }

    /** @return Traversable<Message> */
    public function getIterator(): Traversable
    {
        if ($this->result === null || $this->generator === null) {
            throw new StreamClosed();
        }

        return $this->generator;
    }

    /** @return Generator<Message> */
    private function buildGenerator(
        Result $result,
        EventSerializer $eventSerializer,
        HeadersSerializer $headersSerializer,
        AbstractPlatform $platform,
    ): Generator {
        /** @var array{id: positive-int, aggregate: string, aggregate_id: string, playhead: int|string, event: string, payload: string, recorded_on: string, archived: int|string, new_stream_start: int|string, custom_headers: string} $data */
        foreach ($result->fetchAllAssociative() as $data) {
            if ($this->position === null) {
                $this->position = 0;
            } else {
                ++$this->position;
            }

            $this->index = $data['id'];
            $event = $eventSerializer->deserialize(new SerializedEvent($data['event'], $data['payload']));

            $customHeaders = $headersSerializer->deserialize(DoctrineHelper::normalizeCustomHeaders($data['custom_headers'], $platform));

            yield Message::create($event)
                ->withHeader(new AggregateHeader(
                    $data['aggregate'],
                    $data['aggregate_id'],
                    DoctrineHelper::normalizePlayhead($data['playhead'], $platform),
                    DoctrineHelper::normalizeRecordedOn($data['recorded_on'], $platform),
                ))
                ->withHeader(new ArchivedHeader(DoctrineHelper::normalizeArchived($data['archived'], $platform)))
                ->withHeader(new NewStreamStartHeader(DoctrineHelper::normalizeNewStreamStart($data['new_stream_start'], $platform)))
                ->withHeaders($customHeaders);
        }
    }
}
