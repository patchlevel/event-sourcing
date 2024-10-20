<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Translator;

use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\StreamHeader;

/** @experimental */
final class AggregateToStreamHeaderTranslator implements Translator
{
    /** @return list<Message> */
    public function __invoke(Message $message): array
    {
        if (!$message->hasHeader(AggregateHeader::class)) {
            return [$message];
        }

        $aggregateHeader = $message->header(AggregateHeader::class);

        return [
            $message
                ->removeHeader(AggregateHeader::class)
                ->withHeader(new StreamHeader(
                    $aggregateHeader->streamName(),
                    $aggregateHeader->playhead,
                    $aggregateHeader->recordedOn,
                )),
        ];
    }
}
