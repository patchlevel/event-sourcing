<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Translator;

use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;

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
                ->withHeader(new StreamNameHeader($aggregateHeader->streamName()))
                ->withHeader(new PlayheadHeader($aggregateHeader->playhead))
                ->withHeader(new RecordedOnHeader($aggregateHeader->recordedOn)),
        ];
    }
}
