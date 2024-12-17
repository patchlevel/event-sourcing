<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription;

use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToIndexCriterion;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\Stream;

use function array_map;
use function array_values;

final class Lookup
{
    public function __construct(
        private readonly Store $store,
        private readonly EventRegistry $eventRegistry,
        private readonly int $currentIndex,
    ) {
    }

    /** @param class-string ...$events */
    public function lookBack(string ...$events): Stream
    {
        return $this->store->load(
            new Criteria(
                $this->eventsCriteria(...$events),
                new ToIndexCriterion($this->currentIndex),
            ),
            backwards: true,
        );
    }

    /** @param class-string ...$events */
    public function lookAgain(string ...$events): Stream
    {
        return $this->store->load(
            new Criteria(
                $this->eventsCriteria(...$events),
                new ToIndexCriterion($this->currentIndex),
            ),
        );
    }

    /** @param class-string ...$events */
    private function eventsCriteria(string ...$events): EventsCriterion
    {
        return new EventsCriterion(
            array_values(
                array_map(
                    fn (string $event) => $this->eventRegistry->eventName($event),
                    $events,
                ),
            ),
        );
    }
}
