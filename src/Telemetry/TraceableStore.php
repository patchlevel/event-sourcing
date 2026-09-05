<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Telemetry;

use Closure;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\AppendStore;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Store\Store;

use function count;
use function is_array;
use function iterator_to_array;

/**
 * The AppendStore methods are forwarded to the wrapped store, so that a wrapped store can
 * be used wherever an AppendStore is expected. Calling them on a store which does not
 * implement AppendStore throws a StoreDoesNotSupport exception.
 */
final class TraceableStore implements Store, AppendStore
{
    private readonly Instrumentation $instrumentation;

    public function __construct(
        private readonly Store $store,
        TracerProviderInterface|null $tracerProvider = null,
    ) {
        $this->instrumentation = new Instrumentation($tracerProvider);
    }

    public function load(
        Criteria|null $criteria = null,
        int|null $limit = null,
        int|null $offset = null,
        bool $backwards = false,
    ): Stream {
        return $this->instrumentation->span(
            'event_sourcing.store.load',
            fn (): Stream => $this->store->load($criteria, $limit, $offset, $backwards),
        );
    }

    public function count(Criteria|null $criteria = null): int
    {
        return $this->instrumentation->span(
            'event_sourcing.store.count',
            fn (): int => $this->store->count($criteria),
        );
    }

    public function save(Message ...$messages): void
    {
        $this->instrumentation->span(
            'event_sourcing.store.save',
            function () use ($messages): void {
                $this->store->save(...$messages);
            },
            SpanKind::KIND_PRODUCER,
            [
                TraceAttributes::MESSAGING_SYSTEM => TraceAttributes::SYSTEM,
                TraceAttributes::MESSAGING_OPERATION_NAME => 'save',
                TraceAttributes::MESSAGING_OPERATION_TYPE => TraceAttributes::OPERATION_TYPE_SEND,
                TraceAttributes::MESSAGING_BATCH_MESSAGE_COUNT => count($messages),
            ],
        );
    }

    /**
     * @param Closure():ClosureReturn $function
     *
     * @template ClosureReturn
     */
    public function transactional(Closure $function): void
    {
        $this->instrumentation->span(
            'event_sourcing.store.transactional',
            function () use ($function): void {
                $this->store->transactional($function);
            },
        );
    }

    /** @return list<string> */
    public function streams(): array
    {
        return $this->instrumentation->span(
            'event_sourcing.store.streams',
            fn (): array => $this->store->streams(),
        );
    }

    public function remove(Criteria|null $criteria = null): void
    {
        $this->instrumentation->span(
            'event_sourcing.store.remove',
            function () use ($criteria): void {
                $this->store->remove($criteria);
            },
        );
    }

    public function archive(Criteria|null $criteria = null): void
    {
        $this->instrumentation->span(
            'event_sourcing.store.archive',
            function () use ($criteria): void {
                $this->store->archive($criteria);
            },
        );
    }

    /** @param iterable<Message> $messages */
    public function append(iterable $messages, AppendCondition|null $appendCondition = null): void
    {
        $store = $this->appendStore();

        // the messages are counted for the span attribute, so a generator has to be materialized
        $messages = is_array($messages) ? $messages : iterator_to_array($messages, false);

        $this->instrumentation->span(
            'event_sourcing.store.append',
            static function () use ($store, $messages, $appendCondition): void {
                $store->append($messages, $appendCondition);
            },
            SpanKind::KIND_PRODUCER,
            [
                TraceAttributes::MESSAGING_SYSTEM => TraceAttributes::SYSTEM,
                TraceAttributes::MESSAGING_OPERATION_NAME => 'append',
                TraceAttributes::MESSAGING_OPERATION_TYPE => TraceAttributes::OPERATION_TYPE_SEND,
                TraceAttributes::MESSAGING_BATCH_MESSAGE_COUNT => count($messages),
            ],
        );
    }

    public function query(Query $query): Stream
    {
        $store = $this->appendStore();

        return $this->instrumentation->span(
            'event_sourcing.store.query',
            static fn (): Stream => $store->query($query),
        );
    }

    private function appendStore(): AppendStore
    {
        if (!$this->store instanceof AppendStore) {
            throw StoreDoesNotSupport::interface($this->store, AppendStore::class);
        }

        return $this->store;
    }
}
