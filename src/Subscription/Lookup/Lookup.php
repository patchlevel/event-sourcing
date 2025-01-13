<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Lookup;

use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Store\Criteria\AggregateIdCriterion;
use Patchlevel\EventSourcing\Store\Criteria\AggregateNameCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToIndexCriterion;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\Stream;

use function array_map;
use function array_values;

final class Lookup
{
    private bool $backwards = false;

    private Criteria $criteria;

    public function __construct(
        private readonly Store $store,
        private readonly Message $currentMessage,
        private readonly EventRegistry|null $eventRegistry = null,
    ) {
        $this->criteria = new Criteria();
    }

    /**
     * @param class-string|string ...$events
     *
     * @return $this
     */
    public function events(string ...$events): self
    {
        $this->criteria = $this->criteria->add(
            new EventsCriterion(
                array_values(
                    array_map(
                        function (string $event): string {
                            if ($this->eventRegistry && $this->eventRegistry->hasEventClass($event)) {
                                return $this->eventRegistry->eventName($event);
                            }

                            return $event;
                        },
                        $events,
                    ),
                ),
            ),
        );

        return $this;
    }

    /** @return $this */
    public function stream(string|null $stream = null): self
    {
        if ($stream === null) {
            $this->criteria = $this->criteria->remove(StreamCriterion::class);
        } else {
            $this->criteria = $this->criteria->add(new StreamCriterion($stream));
        }

        return $this;
    }

    /** @return $this */
    public function currentStream(): self
    {
        $stream = $this->currentMessage->header(StreamNameHeader::class)->streamName;
        $this->criteria = $this->criteria->add(new StreamCriterion($stream));

        return $this;
    }

    /** @return $this */
    public function aggregateName(string|null $aggregateName = null): self
    {
        if ($aggregateName === null) {
            $this->criteria = $this->criteria->remove(AggregateNameCriterion::class);
        } else {
            $this->criteria = $this->criteria->add(new AggregateNameCriterion($aggregateName));
        }

        return $this;
    }

    /** @return $this */
    public function aggregateId(string|null $aggregateId = null): self
    {
        if ($aggregateId === null) {
            $this->criteria = $this->criteria->remove(AggregateIdCriterion::class);
        } else {
            $this->criteria = $this->criteria->add(new AggregateIdCriterion($aggregateId));
        }

        return $this;
    }

    public function currentAggregate(): self
    {
        $aggregateHeader = $this->currentMessage->header(AggregateHeader::class);
        $this->criteria = $this->criteria
            ->add(new AggregateNameCriterion($aggregateHeader->aggregateName))
            ->add(new AggregateIdCriterion($aggregateHeader->aggregateId));

        return $this;
    }

    /** @return $this */
    public function forward(): self
    {
        $this->backwards = false;

        return $this;
    }

    /** @return $this */
    public function backwards(): self
    {
        $this->backwards = true;

        return $this;
    }

    public function fetchAll(): Stream
    {
        return $this->store->load(
            $this->criteria->add(
                new ToIndexCriterion($this->currentMessage->header(IndexHeader::class)->index),
            ),
            backwards: $this->backwards,
        );
    }

    public function fetchFirst(): Message
    {
        $stream = $this->fetchAll();

        $message = $stream->current();

        if ($message === null) {
            throw new MessageNotFound();
        }

        return $message;
    }

    public function fetchLast(): Message
    {
        $stream = $this->fetchAll();

        while (!$stream->end()) {
            $stream->next();
        }

        $message = $stream->current();

        if ($message === null) {
            throw new MessageNotFound();
        }

        return $message;
    }

    /** @return $this */
    public function reset(): self
    {
        $this->criteria = new Criteria();
        $this->backwards = false;

        return $this;
    }
}
