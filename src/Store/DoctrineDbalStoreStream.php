<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use Generator;
use IteratorAggregate;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\EventBus\Serializer\SerializedHeader;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Traversable;

use function array_map;

/** @implements IteratorAggregate<Message> */
final class DoctrineDbalStoreStream implements Stream, IteratorAggregate
{
    /** @var Generator<Message> */
    private readonly Generator $generator;

    /** @var positive-int|0|null */
    private int|null $position;

    /** @var positive-int|null */
    private int|null $index;

    public function __construct(
        private readonly Result $result,
        EventSerializer $eventSerializer,
        HeadersSerializer $headersSerializer,
        AbstractPlatform $platform,
    ) {
        $this->generator = $this->buildGenerator($result, $eventSerializer, $headersSerializer, $platform);
        $this->position = null;
        $this->index = null;
    }

    public function close(): void
    {
        $this->result->free();
    }

    public function next(): void
    {
        $this->generator->next();
    }

    public function end(): bool
    {
        return !$this->generator->valid();
    }

    public function current(): Message|null
    {
        return $this->generator->current() ?: null;
    }

    /** @return positive-int|0|null */
    public function position(): int|null
    {
        if ($this->position === null) {
            $this->generator->key();
        }

        return $this->position;
    }

    /** @return positive-int|null */
    public function index(): int|null
    {
        if ($this->index === null) {
            $this->generator->key();
        }

        return $this->index;
    }

    /** @return Traversable<Message> */
    public function getIterator(): Traversable
    {
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
        foreach ($result->iterateAssociative() as $data) {
            if ($this->position === null) {
                $this->position = 0;
            } else {
                ++$this->position;
            }

            $this->index = $data['id'];
            $event = $eventSerializer->deserialize(new SerializedEvent($data['event'], $data['payload']));

            $customHeaders = $headersSerializer->deserialize(array_map(
                /** @param array{name: string, payload: string} $customHeader */
                static fn (array $customHeader) => new SerializedHeader($customHeader['name'], $customHeader['payload']),
                DoctrineHelper::normalizeCustomHeaders($data['custom_headers'], $platform),
            ));

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
