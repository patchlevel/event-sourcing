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

    /** @var positive-int|0|null */
    private int|null $position;

    /** @var positive-int|null */
    private int|null $index;

    public function __construct(
        private readonly Result $result,
        EventSerializer $serializer,
        AggregateRootRegistry $aggregateRootRegistry,
        AbstractPlatform $platform,
    ) {
        $this->generator = $this->buildGenerator($result, $serializer, $aggregateRootRegistry, $platform);
        $this->position = null;
        $this->index = null;
    }

    public function close(): void
    {
        $this->result->free();
    }

    public function current(): Message|null
    {
        return $this->generator->current() ?: null;
    }

    /** @return positive-int|0|null */
    public function position(): int|null
    {
        if (!$this->position) {
            $this->generator->key();
        }

        return $this->position;
    }

    /** @return positive-int|null */
    public function index(): int|null
    {
        if (!$this->index) {
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
        EventSerializer $serializer,
        AggregateRootRegistry $aggregateRootRegistry,
        AbstractPlatform $platform,
    ): Generator {
        /** @var array{id: positive-int, aggregate: string, aggregate_id: string, playhead: int|string, event: string, payload: string, recorded_on: string, custom_headers: string} $data */
        foreach ($result->iterateAssociative() as $data) {
            if ($this->position === null) {
                $this->position = 0;
            } else {
                ++$this->position;
            }

            $this->index = $data['id'];
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
