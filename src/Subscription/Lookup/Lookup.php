<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Lookup;

use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Store\Criteria\AggregateIdCriterion;
use Patchlevel\EventSourcing\Store\Criteria\AggregateNameCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToIndexCriterion;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamStore;

final class Lookup
{
    public function __construct(
        private readonly Store $store,
        private readonly EventRegistry $eventRegistry,
        private readonly Message $currentMessage,
    ) {
    }

    public function query(): LookupQuery
    {
        $criteria = new Criteria();

        try {
            $index = $this->currentMessage->header(IndexHeader::class)->index;
            $criteria->add(new ToIndexCriterion($index));
        } catch (HeaderNotFound) {
            throw new MissingIndex();
        }

        if ($this->store instanceof StreamStore) {
            $stream = $this->currentMessage->header(StreamNameHeader::class)->streamName;
            $criteria->add(new StreamCriterion($stream));
        } else {
            $aggregateHeader = $this->currentMessage->header(AggregateHeader::class);
            $criteria->add(new AggregateNameCriterion($aggregateHeader->aggregateName));
            $criteria->add(new AggregateIdCriterion($aggregateHeader->aggregateId));
        }

        return new LookupQuery(
            $this->store,
            $this->eventRegistry,
            $criteria,
        );
    }
}
