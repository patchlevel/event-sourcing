<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Closure;
use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;

use function array_filter;
use function array_map;
use function array_push;
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

final class InMemoryStore implements Store
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
                                return $message->header(StreamNameHeader::class)->streamName;
                            } catch (HeaderNotFound) {
                                return null;
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
                        case StreamCriterion::class:
                            if ($criterion->all()) {
                                break;
                            }

                            try {
                                $messageStreamName = $message->header(StreamNameHeader::class)->streamName;
                            } catch (HeaderNotFound) {
                                return false;
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
                                $playhead = $message->header(PlayheadHeader::class)->playhead;
                            } catch (HeaderNotFound) {
                                return false;
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
