<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\EventBus\Message;

use function array_key_exists;

final class RecalculatePlayheadMiddleware implements Middleware
{
    /** @var array<string, array<string, positive-int>> */
    private array $index = [];

    /** @return list<Message> */
    public function __invoke(Message $message): array
    {
        $header = $message->header(AggregateHeader::class);
        $playhead = $this->nextPlayhead($header->aggregateName, $header->aggregateId);

        if ($header->playhead === $playhead) {
            return [$message];
        }

        $header = $message->header(AggregateHeader::class);

        return [
            $message->withHeader(new AggregateHeader(
                $header->aggregateName,
                $header->aggregateId,
                $playhead,
                $header->recordedOn,
            )),
        ];
    }

    public function reset(): void
    {
        $this->index = [];
    }

    /** @return positive-int */
    private function nextPlayhead(string $aggregateName, string $aggregateId): int
    {
        if (!array_key_exists($aggregateName, $this->index)) {
            $this->index[$aggregateName] = [];
        }

        if (!array_key_exists($aggregateId, $this->index[$aggregateName])) {
            $this->index[$aggregateName][$aggregateId] = 1;
        } else {
            $this->index[$aggregateName][$aggregateId]++;
        }

        return $this->index[$aggregateName][$aggregateId];
    }
}
