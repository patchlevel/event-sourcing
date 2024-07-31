<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Closure;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Criteria\AggregateIdCriterion;
use Patchlevel\EventSourcing\Store\Criteria\AggregateNameCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;

use function array_filter;
use function array_map;
use function array_push;
use function array_reverse;
use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function mb_substr;
use function str_ends_with;
use function str_starts_with;

use const ARRAY_FILTER_USE_BOTH;

final class InMemoryStore implements StreamStore
{
    /** @param array<positive-int|0, Message> $messages */
    public function __construct(
        private array $messages = [],
    ) {
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
        array_push($this->messages, ...$messages);
    }

    /**
     * @param Closure():ClosureReturn $function
     *
     * @template ClosureReturn
     */
    public function transactional(Closure $function): void
    {
        $function();
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
                                    return $message->header(StreamHeader::class)->streamName;
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

    public function remove(string $streamName): void
    {
        $this->messages = array_values(
            array_filter(
                $this->messages,
                static function (Message $message) use ($streamName): bool {
                    try {
                        return $message->header(AggregateHeader::class)->streamName() !== $streamName;
                    } catch (HeaderNotFound) {
                        try {
                            return $message->header(StreamHeader::class)->streamName !== $streamName;
                        } catch (HeaderNotFound) {
                            return true;
                        }
                    }
                },
            ),
        );
    }

    /** @return array<positive-int|0, Message> */
    private function filter(Criteria|null $criteria): array
    {
        if (!$criteria) {
            return $this->messages;
        }

        return array_filter(
            $this->messages,
            static function (Message $message, int $index) use ($criteria): bool {
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
                            if ($criterion->streamName === '*') {
                                break;
                            }

                            try {
                                $messageStreamName = $message->header(AggregateHeader::class)->streamName();
                            } catch (HeaderNotFound) {
                                try {
                                    $messageStreamName = $message->header(StreamHeader::class)->streamName;
                                } catch (HeaderNotFound) {
                                    return false;
                                }
                            }

                            if (str_ends_with($criterion->streamName, '*')) {
                                if (!str_starts_with($messageStreamName, mb_substr($criterion->streamName, 0, -1))) {
                                    return false;
                                }

                                break;
                            }

                            if ($messageStreamName !== $criterion->streamName) {
                                return false;
                            }

                            break;
                        case FromPlayheadCriterion::class:
                            $playhead = null;

                            try {
                                $playhead = $message->header(AggregateHeader::class)->playhead;
                            } catch (HeaderNotFound) {
                                try {
                                    $playhead = $message->header(StreamHeader::class)->playhead;
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
                            if ($index < $criterion->fromIndex) {
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
