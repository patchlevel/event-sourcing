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

    /** @param class-string|string ...$events */
    public function events(string ...$events): self
    {
        $self = clone $this;

        $self->criteria = $self->criteria->add(
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

        return $self;
    }

    public function stream(string|null $stream = null): self
    {
        $self = clone $this;

        if ($stream === null) {
            $self->criteria = $self->criteria->remove(StreamCriterion::class);
        } else {
            $self->criteria = $self->criteria->add(new StreamCriterion($stream));
        }

        return $self;
    }

    public function currentStream(): self
    {
        $self = clone $this;

        $stream = $self->currentMessage->header(StreamNameHeader::class)->streamName;
        $self->criteria = $self->criteria->add(new StreamCriterion($stream));

        return $self;
    }

    public function aggregateName(string|null $aggregateName = null): self
    {
        $self = clone $this;

        if ($aggregateName === null) {
            $self->criteria = $self->criteria->remove(AggregateNameCriterion::class);
        } else {
            $self->criteria = $self->criteria->add(new AggregateNameCriterion($aggregateName));
        }

        return $self;
    }

    public function aggregateId(string|null $aggregateId = null): self
    {
        $self = clone $this;

        if ($aggregateId === null) {
            $self->criteria = $self->criteria->remove(AggregateIdCriterion::class);
        } else {
            $self->criteria = $self->criteria->add(new AggregateIdCriterion($aggregateId));
        }

        return $self;
    }

    public function currentAggregate(): self
    {
        $self = clone $this;

        $aggregateHeader = $self->currentMessage->header(AggregateHeader::class);
        $self->criteria = $self->criteria
            ->add(new AggregateNameCriterion($aggregateHeader->aggregateName))
            ->add(new AggregateIdCriterion($aggregateHeader->aggregateId));

        return $self;
    }

    public function forward(): self
    {
        $self = clone $this;

        $self->backwards = false;

        return $self;
    }

    public function backwards(): self
    {
        $self = clone $this;

        $self->backwards = true;

        return $self;
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
        $stream = $this->store->load(
            $this->criteria->add(
                new ToIndexCriterion($this->currentMessage->header(IndexHeader::class)->index),
            ),
            limit: 1,
            backwards: $this->backwards,
        );

        $message = $stream->current();

        if ($message === null) {
            throw new MessageNotFound();
        }

        return $message;
    }

    public function fetchLast(): Message
    {
        $stream = $this->store->load(
            $this->criteria->add(
                new ToIndexCriterion($this->currentMessage->header(IndexHeader::class)->index),
            ),
            limit: 1,
            backwards: !$this->backwards,
        );

        $message = $stream->current();

        if ($message === null) {
            throw new MessageNotFound();
        }

        return $message;
    }
}
