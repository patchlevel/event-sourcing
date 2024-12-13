<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Translator;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;

final class UntilEventTranslator implements Translator
{
    public function __construct(
        private readonly DateTimeImmutable $until,
    ) {
    }

    /** @return list<Message> */
    public function __invoke(Message $message): array
    {
        if ($message->hasHeader(AggregateHeader::class)) {
            $header = $message->header(AggregateHeader::class);

            if ($header->recordedOn < $this->until) {
                return [$message];
            }

            return [];
        }

        if ($message->hasHeader(RecordedOnHeader::class)) {
            $header = $message->header(RecordedOnHeader::class);

            if ($header->recordedOn < $this->until) {
                return [$message];
            }

            return [];
        }

        return [$message];
    }
}
