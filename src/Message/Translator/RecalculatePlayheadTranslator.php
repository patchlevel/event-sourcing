<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Translator;

use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;

use function array_key_exists;

final class RecalculatePlayheadTranslator implements Translator
{
    /** @var array<string, positive-int> */
    private array $index = [];

    /** @return list<Message> */
    public function __invoke(Message $message): array
    {
        if ($message->hasHeader(StreamNameHeader::class) && $message->hasHeader(PlayheadHeader::class)) {
            $streamName = $message->header(StreamNameHeader::class)->streamName;

            $playhead = $this->nextPlayhead($streamName);

            return [
                $message->withHeader(new PlayheadHeader($playhead)),
            ];
        }

        if ($message->hasHeader(AggregateHeader::class)) {
            $header = $message->header(AggregateHeader::class);

            return [
                $message->withHeader(new AggregateHeader(
                    $header->aggregateName,
                    $header->aggregateId,
                    $this->nextPlayhead($header->streamName()),
                    $header->recordedOn,
                )),
            ];
        }

        return [$message];
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
