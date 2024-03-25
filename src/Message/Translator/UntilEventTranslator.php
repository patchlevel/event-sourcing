<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Translator;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\Message;

final class UntilEventTranslator implements Translator
{
    public function __construct(
        private readonly DateTimeImmutable $until,
    ) {
    }

    /** @return list<Message> */
    public function __invoke(Message $message): array
    {
        $recordedOn = $message->header(AggregateHeader::class)->recordedOn;

        if ($recordedOn < $this->until) {
            return [$message];
        }

        return [];
    }
}
