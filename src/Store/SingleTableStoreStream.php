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

final class SingleTableStoreStream implements Stream, IteratorAggregate
{
    private readonly Result $result;

    /** @var Generator<Message> */
    private readonly Generator $generator;

    private int $position;

    public function __construct(
        Result $result,
        EventSerializer $serializer,
        AggregateRootRegistry $aggregateRootRegistry,
        AbstractPlatform $platform
    ) {
        $this->result = $result;
        $this->generator = $this->buildGenerator($result, $serializer, $aggregateRootRegistry, $platform);
        $this->position = 0;
    }

    public function close(): void
    {
        $this->result->free();
    }

    public function current(): ?Message
    {
        return $this->generator->current() ?: null;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function getIterator(): Traversable
    {
        yield from $this->generator;
    }

    private function buildGenerator(
        Result $result,
        EventSerializer $serializer,
        AggregateRootRegistry $aggregateRootRegistry,
        AbstractPlatform $platform
    ): Generator {
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
