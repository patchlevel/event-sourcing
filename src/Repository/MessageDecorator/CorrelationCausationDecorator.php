<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository\MessageDecorator;

use Patchlevel\EventSourcing\Message\Context\MessageContext;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\CausationIdHeader;
use Patchlevel\EventSourcing\Store\Header\CorrelationIdHeader;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use Ramsey\Uuid\Uuid;

/**
 * Adds correlation and causation ids to every recorded message.
 *
 * The event id is generated here and not in the store, because the causation id
 * of the next message needs a stable id before the message is saved. The stores
 * only generate an event id if the header is missing, so this stays compatible.
 */
final class CorrelationCausationDecorator implements MessageDecorator
{
    public function __construct(
        private readonly MessageContext $messageContext,
    ) {
    }

    public function __invoke(Message $message): Message
    {
        if (!$message->hasHeader(EventIdHeader::class)) {
            $message = $message->withHeader(new EventIdHeader(Uuid::uuid7()->toString()));
        }

        $causationId = $this->messageContext->causationId();

        if ($causationId !== null && !$message->hasHeader(CausationIdHeader::class)) {
            $message = $message->withHeader(new CausationIdHeader($causationId));
        }

        if (!$message->hasHeader(CorrelationIdHeader::class)) {
            $message = $message->withHeader(new CorrelationIdHeader(
                $this->messageContext->correlationId() ?? $message->header(EventIdHeader::class)->eventId,
            ));
        }

        return $message;
    }
}
