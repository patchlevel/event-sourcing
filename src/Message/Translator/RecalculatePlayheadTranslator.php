<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Translator;

use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\StreamHeader;

use function array_key_exists;

final class RecalculatePlayheadTranslator implements Translator
{
    /** @var array<string, positive-int> */
    private array $index = [];

    /** @return list<Message> */
    public function __invoke(Message $message): array
    {
        try {
            $header = $message->header(AggregateHeader::class);
        } catch (HeaderNotFound) {
            try {
                $header = $message->header(StreamHeader::class);
            } catch (HeaderNotFound) {
                return [$message];
            }
        }

        $stream = $header instanceof StreamHeader ? $header->streamName : $header->streamName();

        $playhead = $this->nextPlayhead($stream);

        if ($header->playhead === $playhead) {
            return [$message];
        }

        if ($header instanceof StreamHeader) {
            return [
                $message->withHeader(new StreamHeader(
                    $header->streamName,
                    $playhead,
                    $header->recordedOn,
                )),
            ];
        }

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
    private function nextPlayhead(string $stream): int
    {
        if (!array_key_exists($stream, $this->index)) {
            $this->index[$stream] = 1;
        } else {
            $this->index[$stream]++;
        }

        return $this->index[$stream];
    }
}
