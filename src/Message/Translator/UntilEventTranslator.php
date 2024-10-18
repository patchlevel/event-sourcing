<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Translator;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\StreamHeader;

/** @deprecated use Patchlevel\EventSourcing\Pipeline\Middleware\UntilEventMiddleware instead */
final class UntilEventTranslator implements Translator
{
    public function __construct(
        private readonly DateTimeImmutable $until,
    ) {
    }

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

        $recordedOn = $header->recordedOn;

        if ($recordedOn < $this->until) {
            return [$message];
        }

        return [];
    }
}
