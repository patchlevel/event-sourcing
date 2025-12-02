<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Closure;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Store\Criteria\AggregateIdCriterion;
use Patchlevel\EventSourcing\Store\Criteria\AggregateNameCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToIndexCriterion;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\Uuid;
use Throwable;

use function array_filter;
use function array_map;
use function array_reverse;
use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function mb_substr;
use function str_ends_with;
use function str_starts_with;

use const ARRAY_FILTER_USE_BOTH;

final class InMemoryStore implements StreamStore
{
    /** @var array<positive-int, Message> */
    private array $messages = [];

    /** @param list<Message> $messages */
    public function __construct(
        array $messages = [],
        private readonly EventRegistry|null $eventRegistry = null,
        private readonly ClockInterface $clock = new SystemClock(),
    ) {
        $this->save(...$messages);
    }

    public function load(
        Criteria|null $criteria = null,
        int|null $limit = null,
        int|null $offset = null,
        bool $backwards = false,
    ): ArrayStream {
        $messages = $this->filter($criteria);

        if ($backwards) {
            $messages = array_reverse($messages);
        }

        if ($offset !== null) {
            $messages = array_slice($messages, $offset);
        }

        if ($limit !== null) {
            $messages = array_slice($messages, 0, $limit);
        }

        return new ArrayStream($messages);
    }

    public function count(Criteria|null $criteria = null): int
    {
        return count($this->filter($criteria));
    }

    public function save(Message ...$messages): void
    {
        $this->transactional(function () use ($messages): void {
            $count = count($this->messages);

            foreach ($messages as $message) {
                $count++;

                if (!$message->hasHeader(IndexHeader::class)) {
                    $message = $message->withHeader(new IndexHeader($count));
                }

                if (!$message->hasHeader(EventIdHeader::class)) {
                    $message = $message->withHeader(new EventIdHeader(Uuid::uuid7()->toString()));
                }

                if (!$message->hasHeader(RecordedOnHeader::class)) {
                    $message = $message->withHeader(new RecordedOnHeader($this->clock->now()));
                }

                $this->messages[$count] = $message;
            }
        });
    }

    /**
     * @param Closure():ClosureReturn $function
     *
     * @template ClosureReturn
     */
    public function transactional(Closure $function): void
    {
        $messages = $this->messages;
        try {
            $function();
        } catch (Throwable $e) {
            $this->messages = $messages;

            throw $e;
        }
    }

    /** @return list<string> */
    public function streams(): array
    {
        return array_values(
            array_unique(
                array_filter(
                    array_map(
                        static function (Message $message): string|null {
                            try {
                                return $message->header(AggregateHeader::class)->streamName();
                            } catch (HeaderNotFound) {
                                try {
                                    return $message->header(StreamNameHeader::class)->streamName;
                                } catch (HeaderNotFound) {
                                    return null;
                                }
                            }
                        },
                        $this->messages,
                    ),
                    static fn (string|null $streamName): bool => $streamName !== null,
                ),
            ),
        );
    }

    public function remove(Criteria|null $criteria = null): void
    {
        $messagesToRemove = $this->filter($criteria);

        $this->messages = array_filter(
            $this->messages,
            static fn (Message $message): bool => !in_array($message, $messagesToRemove, true),
        );
    }

    public function archive(Criteria|null $criteria = null): void
    {
        foreach ($this->filter($criteria) as $key => $message) {
            $this->messages[$key] = $message->withHeader(new ArchivedHeader());
        }
    }

    /** @return array<positive-int, Message> */
    private function filter(Criteria|null $criteria): array
    {
        if (!$criteria) {
            return $this->messages;
        }

        $eventRegistry = $this->eventRegistry;

        return array_filter(
            $this->messages,
            static function (Message $message) use ($criteria, $eventRegistry): bool {
                foreach ($criteria->all() as $criterion) {
                    switch ($criterion::class) {
                        case AggregateIdCriterion::class:
                            try {
                                if ($message->header(AggregateHeader::class)->aggregateId !== $criterion->aggregateId) {
                                    return false;
                                }
                            } catch (HeaderNotFound) {
                                return false;
                            }

                            break;
                        case AggregateNameCriterion::class:
                            try {
                                if ($message->header(AggregateHeader::class)->aggregateName !== $criterion->aggregateName) {
                                    return false;
                                }
                            } catch (HeaderNotFound) {
                                return false;
                            }

                            break;
                        case StreamCriterion::class:
                            if ($criterion->all()) {
                                break;
                            }

                            try {
                                $messageStreamName = $message->header(AggregateHeader::class)->streamName();
                            } catch (HeaderNotFound) {
                                try {
                                    $messageStreamName = $message->header(StreamNameHeader::class)->streamName;
                                } catch (HeaderNotFound) {
                                    return false;
                                }
                            }

                            $match = false;

                            foreach ($criterion->streamName as $streamName) {
                                if (str_ends_with($streamName, '*')) {
                                    if (str_starts_with($messageStreamName, mb_substr($streamName, 0, -1))) {
                                        $match = true;

                                        break;
                                    }
                                } else {
                                    if ($streamName === $messageStreamName) {
                                        $match = true;

                                        break;
                                    }
                                }
                            }

                            if (!$match) {
                                return false;
                            }

                            break;
                        case FromPlayheadCriterion::class:
                            $playhead = null;

                            try {
                                $playhead = $message->header(AggregateHeader::class)->playhead;
                            } catch (HeaderNotFound) {
                                try {
                                    $playhead = $message->header(PlayheadHeader::class)->playhead;
                                } catch (HeaderNotFound) {
                                    return false;
                                }
                            }

                            if ($playhead < $criterion->fromPlayhead) {
                                return false;
                            }

                            break;
                        case ArchivedCriterion::class:
                            if (!$message->hasHeader(ArchivedHeader::class) === $criterion->archived) {
                                return false;
                            }

                            break;
                        case FromIndexCriterion::class:
                            try {
                                $index = $message->header(IndexHeader::class)->index;
                            } catch (HeaderNotFound) {
                                return false;
                            }

                            if ($index <= $criterion->fromIndex) {
                                return false;
                            }

                            break;
                        case ToIndexCriterion::class:
                            try {
                                $index = $message->header(IndexHeader::class)->index;
                            } catch (HeaderNotFound) {
                                return false;
                            }

                            if ($index >= $criterion->toIndex) {
                                return false;
                            }

                            break;
                        case EventsCriterion::class:
                            if ($eventRegistry === null) {
                                throw new MissingEventRegistry($criterion::class);
                            }

                            if (!in_array($eventRegistry->eventName($message->event()::class), $criterion->events)) {
                                return false;
                            }

                            break;
                        default:
                            throw new UnsupportedCriterion($criterion::class);
                    }
                }

                return true;
            },
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function clear(): void
    {
        $this->messages = [];
    }
}
