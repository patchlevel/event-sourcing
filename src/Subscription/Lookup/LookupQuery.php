<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Lookup;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Store\Criteria\AggregateIdCriterion;
use Patchlevel\EventSourcing\Store\Criteria\AggregateNameCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\Stream;

use function array_map;
use function array_values;

final class LookupQuery
{
    public function __construct(
        private readonly Store $store,
        private readonly EventRegistry $eventRegistry,
        private readonly Criteria $criteria,
    ) {
    }

    /** @param class-string ...$events */
    public function events(string ...$events): self
    {
        $criterion = new EventsCriterion(
            array_values(
                array_map(
                    fn (string $event): string => $this->eventRegistry->eventName($event),
                    $events,
                ),
            ),
        );

        return new self(
            $this->store,
            $this->eventRegistry,
            $this->criteria->add($criterion),
        );
    }

    public function stream(string $stream): self
    {
        return new self(
            $this->store,
            $this->eventRegistry,
            $this->criteria->add(new StreamCriterion($stream)),
        );
    }

    public function aggregate(string $aggregateName, string $aggregateId): self
    {
        return new self(
            $this->store,
            $this->eventRegistry,
            $this->criteria
                ->add(new AggregateNameCriterion($aggregateName))
                ->add(new AggregateIdCriterion($aggregateId)),
        );
    }

    /** @return ($one is true ? Message : Stream) */
    public function lookBack(bool $one = false): Stream|Message
    {
        $stream = $this->store->load(
            $this->criteria,
            backwards: true,
        );

        if ($one) {
            $message = $stream->current();

            if ($message === null) {
                throw new MessageNotFound();
            }

            return $message;
        }

        return $stream;
    }

    /** @return ($one is true ? Message : Stream) */
    public function lookAgain(bool $one = false): Stream|Message
    {
        $stream = $this->store->load($this->criteria);

        if ($one) {
            $message = $stream->current();

            if ($message === null) {
                throw new MessageNotFound();
            }

            return $message;
        }

        return $stream;
    }
}
