<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DecisionModel;

use Patchlevel\EventSourcing\Projection\CheckpointPositions;
use Patchlevel\EventSourcing\Projection\CompositeProjection;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\AppendStore;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Store\SubQuery;

use function max;
use function min;

/**
 * Collects everything a decision reads and appends the resulting events with a matching append condition.
 * Use a new decision for each command.
 *
 * @experimental
 */
final class Decision
{
    private readonly EventAppender $eventAppender;

    /** @var list<SubQuery> */
    private array $subQueries = [];

    private bool $readsEverything = false;

    /** @var positive-int|0|null */
    private int|null $after = null;

    private bool $appended = false;

    public function __construct(
        private readonly AppendStore $store,
        EventAppender|null $eventAppender = null,
    ) {
        $this->eventAppender = $eventAppender ?? new StoreEventAppender($store);
    }

    /**
     * Reads the state of the projection and adds its query to the append condition.
     * Pass an array of projections to read them with one query, the states are returned with the same keys.
     * Later reads may depend on the state of earlier ones.
     *
     * @param Projection<S>|array<K, Projection> $projections
     *
     * @return ($projections is Projection ? S : array<K, mixed>)
     *
     * @template S
     * @template K of array-key
     */
    public function read(Projection|array $projections): mixed
    {
        if (!$projections instanceof Projection) {
            return $this->readAll($projections);
        }

        /** @var S $state */
        $state = $this->readAll([$projections])[0];

        return $state;
    }

    /**
     * Appends the events, but only if nothing the decision has read has changed in the meantime.
     * Without any read, the events are appended without a condition.
     *
     * @param list<object> $events
     *
     * @throws DecisionAlreadyAppended
     */
    public function append(array $events, string|null $streamName = null): void
    {
        if ($this->appended) {
            throw new DecisionAlreadyAppended();
        }

        $this->eventAppender->append($events, $this->appendCondition(), $streamName);
        $this->appended = true;
    }

    public function appendCondition(): AppendCondition|null
    {
        if ($this->after === null) {
            return null;
        }

        if ($this->readsEverything) {
            return new AppendCondition(new Query(), $this->after);
        }

        return new AppendCondition(
            (new Query(...$this->subQueries))->optimize(),
            $this->after,
        );
    }

    /**
     * @param array<K, Projection> $projections
     *
     * @return array<K, mixed>
     *
     * @template K of array-key
     */
    private function readAll(array $projections): array
    {
        if ($this->appended) {
            throw new DecisionAlreadyAppended();
        }

        if ($projections === []) {
            return [];
        }

        $from = CheckpointPositions::resolve($this->store, $projections);
        $projection = new CompositeProjection($projections, $from);
        $query = $projection->query();

        // Everything up to the head of the first read is visible to this and every later read,
        // so it is the `after` of the whole decision.
        $after = $this->after ?? $this->store->head();

        $stream = $this->store->query($query, $from === [] ? 0 : min($from));
        $state = $projection->initialState();

        foreach ($stream as $message) {
            if ($this->after === null) {
                $after = max($after, $stream->index() ?? 0);
            }

            $state = $projection->apply($state, $message);
        }

        $this->after = $after;

        if ($query->subQueries === []) {
            $this->readsEverything = true;
        } else {
            $this->subQueries = [...$this->subQueries, ...$query->subQueries];
        }

        /** @var array<K, mixed> $result */
        $result = $state;

        return $result;
    }
}
