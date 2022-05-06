<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\EventBus\Message;

use function array_key_exists;

final class RecalculatePlayheadMiddleware implements Middleware
{
    /** @var array<class-string<AggregateRoot>, array<string, int>> */
    private array $index = [];

    /**
     * @return list<Message>
     */
    public function __invoke(Message $message): array
    {
        $playhead = $this->nextPlayhead($message->aggregateClass(), $message->aggregateId());

        if ($message->playhead() === $playhead) {
            return [$message];
        }

        return [
            $message->withHeader(Message::HEADER_PLAYHEAD, $playhead),
        ];
    }

    /**
     * @param class-string<AggregateRoot> $aggregateClass
     */
    private function nextPlayhead(string $aggregateClass, string $aggregateId): int
    {
        if (!array_key_exists($aggregateClass, $this->index)) {
            $this->index[$aggregateClass] = [];
        }

        if (!array_key_exists($aggregateId, $this->index[$aggregateClass])) {
            $this->index[$aggregateClass][$aggregateId] = 0;
        }

        $this->index[$aggregateClass][$aggregateId]++;

        return $this->index[$aggregateClass][$aggregateId];
    }
}
