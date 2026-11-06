<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Telemetry;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\CausationIdHeader;
use Patchlevel\EventSourcing\Store\Header\CorrelationIdHeader;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;

/**
 * Extracts the span attributes of a message.
 *
 * Only ids and the event name are exposed. The payload is never attached to a span,
 * because it regularly contains personal data.
 *
 * @internal
 */
final class MessageAttributes
{
    /** @return array<string, string|null> */
    public static function from(Message $message): array
    {
        return [
            TraceAttributes::EVENT_NAME => $message->event()::class,
            TraceAttributes::MESSAGING_MESSAGE_ID => $message->hasHeader(EventIdHeader::class)
                ? $message->header(EventIdHeader::class)->eventId
                : null,
            TraceAttributes::MESSAGING_MESSAGE_CONVERSATION_ID => $message->hasHeader(CorrelationIdHeader::class)
                ? $message->header(CorrelationIdHeader::class)->correlationId
                : null,
            TraceAttributes::CAUSATION_ID => $message->hasHeader(CausationIdHeader::class)
                ? $message->header(CausationIdHeader::class)->causationId
                : null,
            TraceAttributes::MESSAGING_DESTINATION_NAME => $message->hasHeader(StreamNameHeader::class)
                ? $message->header(StreamNameHeader::class)->streamName
                : null,
        ];
    }
}
