<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
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
        $this->assertNotClosed();

        $this->generator->next();
    }

    public function end(): bool
    {
        $this->assertNotClosed();

        return !$this->generator->valid();
    }

    public function current(): Message|null
    {
        $this->assertNotClosed();

        return $this->generator->current() ?: null;
    }

    /** @return positive-int|0|null */
    public function position(): int|null
    {
        $this->assertNotClosed();

        if ($this->position === null) {
            $this->generator->key();
        }

        return $this->position;
    }

    /** @return positive-int|null */
    public function index(): int|null
    {
        $this->assertNotClosed();

        if ($this->index === null) {
            $this->generator->key();
        }

        return $this->index;
    }

    /** @return Traversable<Message> */
    public function getIterator(): Traversable
    {
        $this->assertNotClosed();

        return $this->generator;
    }

    /** @return Generator<Message> */
    private function buildGenerator(
        Result $result,
        EventSerializer $eventSerializer,
        HeadersSerializer $headersSerializer,
        AbstractPlatform $platform,
    ): Generator {
        $dateTimeType = Type::getType(Types::DATETIMETZ_IMMUTABLE);

        /** @var array{id: positive-int, aggregate: string, aggregate_id: string, playhead: int|string, event: string, payload: string, recorded_on: string, archived: int|string, new_stream_start: int|string, custom_headers: string} $data */
        foreach ($result->iterateAssociative() as $data) {
            if ($this->position === null) {
                $this->position = 0;
            } else {
                ++$this->position;
            }

            $this->index = $data['id'];
            $event = $eventSerializer->deserialize(new SerializedEvent($data['event'], $data['payload']));

            $message = Message::create($event)
                ->withHeader(new AggregateHeader(
                    $data['aggregate'],
                    $data['aggregate_id'],
                    (int)$data['playhead'],
                    $dateTimeType->convertToPHPValue($data['recorded_on'], $platform),
                ));

            if ($data['archived']) {
                $message = $message->withHeader(new ArchivedHeader());
            }

            if ($data['new_stream_start']) {
                $message = $message->withHeader(new StreamStartHeader());
            }

            if (isset($data['transaction_id'])) {
                $message = $message->withHeader(new TransactionIdHeader((int)$data['transaction_id']));
            }

            $customHeaders = $headersSerializer->deserialize($data['custom_headers']);

            yield $message->withHeaders($customHeaders);
        }
    }

    /**
     * @psalm-assert !null $this->result
     * @psalm-assert !null $this->generator
     */
    private function assertNotClosed(): void
    {
        if ($this->result === null || $this->generator === null) {
            throw new StreamClosed();
        }
    }
}
