<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use Generator;
use IteratorAggregate;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Traversable;

/** @implements IteratorAggregate<Message> */
final class DoctrineDbalStoreStream implements Stream, IteratorAggregate
{
    /** @var Generator<Message> */
    private readonly Generator $generator;

    private int $position;

    public function __construct(
        private readonly Result $result,
        EventSerializer $serializer,
        AggregateRootRegistry $aggregateRootRegistry,
        AbstractPlatform $platform,
    ) {
        $this->generator = $this->buildGenerator($result, $serializer, $aggregateRootRegistry, $platform);
        $this->position = 0;
    }

    public function close(): void
    {
        $this->result->free();
    }

    public function current(): Message|null
    {
        return $this->generator->current() ?: null;
    }

    public function position(): int
    {
        return $this->position;
    }

    /** @return Traversable<Message> */
    public function getIterator(): Traversable
    {
        yield from $this->generator;
    }

    /** @return Generator<Message> */
    private function buildGenerator(
        Result $result,
        EventSerializer $serializer,
        AggregateRootRegistry $aggregateRootRegistry,
        AbstractPlatform $platform,
    ): Generator {
        /** @var array{aggregate: string, aggregate_id: string, playhead: int|string, event: string, payload: string, recorded_on: string, custom_headers: string} $data */
        foreach ($result->iterateAssociative() as $data) {
            $this->position++;

            $event = $serializer->deserialize(new SerializedEvent($data['event'], $data['payload']));

            yield Message::create($event)
                ->withAggregateClass($aggregateRootRegistry->aggregateClass($data['aggregate']))
                ->withAggregateId($data['aggregate_id'])
                ->withPlayhead(DoctrineHelper::normalizePlayhead($data['playhead'], $platform))
                ->withRecordedOn(DoctrineHelper::normalizeRecordedOn($data['recorded_on'], $platform))
                ->withCustomHeaders(DoctrineHelper::normalizeCustomHeaders($data['custom_headers'], $platform));
        }
    }
}
