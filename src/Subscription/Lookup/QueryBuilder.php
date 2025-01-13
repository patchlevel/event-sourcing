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

final class QueryBuilder
{
    private bool $backwards = false;

    public function __construct(
        private readonly Store $store,
        private readonly EventRegistry $eventRegistry,
        private Criteria $criteria,
    ) {
    }

    /**
     * @param class-string ...$events
     *
     * @return $this
     */
    public function events(string ...$events): self
    {
        $this->criteria = $this->criteria->add(
            new EventsCriterion(
                array_values(
                    array_map(
                        fn (string $event): string => $this->eventRegistry->eventName($event),
                        $events,
                    ),
                ),
            ),
        );

        return $this;
    }

    /** @return $this */
    public function stream(string $stream): self
    {
        $this->criteria = $this->criteria->add(new StreamCriterion($stream));

        return $this;
    }

    /** @return $this */
    public function aggregate(string $aggregateName, string $aggregateId): self
    {
        $this->criteria = $this->criteria
            ->add(new AggregateNameCriterion($aggregateName))
            ->add(new AggregateIdCriterion($aggregateId));

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

    public function fetch(): Stream
    {
        return $this->store->load(
            $this->criteria,
            backwards: $this->backwards,
        );
    }

    public function fetchOne(): Message
    {
        $stream = $this->fetch();

        $message = $stream->current();

        if ($message === null) {
            throw new MessageNotFound();
        }

        return $message;
    }
}
