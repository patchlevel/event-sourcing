<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Context;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\CorrelationIdHeader;
use Patchlevel\EventSourcing\Store\Header\EventIdHeader;

use function array_pop;
use function count;

/**
 * Ambient context of the message which is currently being handled.
 *
 * The context is a stack, so nested handling (a subscriber dispatching a command
 * which in turn saves an aggregate) keeps the ids of the outer message intact.
 *
 * This is per process state and not fiber aware.
 */
final class MessageContext
{
    /** @var list<array{causationId: string|null, correlationId: string|null}> */
    private array $stack = [];

    /**
     * Push the ids of the given message onto the stack.
     *
     * A message without a correlation id, like one recorded before the ids were
     * introduced, is the root of its own correlation: everything caused by it
     * shares its event id as correlation id. Messages without any headers push
     * an empty frame, so that every push is still balanced by exactly one pop.
     */
    public function pushMessage(Message $message): void
    {
        $eventId = $message->hasHeader(EventIdHeader::class) ? $message->header(EventIdHeader::class)->eventId : null;

        $this->push(
            $eventId,
            $message->hasHeader(CorrelationIdHeader::class)
                ? $message->header(CorrelationIdHeader::class)->correlationId
                : $eventId,
        );
    }

    public function push(string|null $causationId = null, string|null $correlationId = null): void
    {
        $this->stack[] = [
            'causationId' => $causationId,
            'correlationId' => $correlationId,
        ];
    }

    public function pop(): void
    {
        array_pop($this->stack);
    }

    public function clear(): void
    {
        $this->stack = [];
    }

    public function causationId(): string|null
    {
        return $this->current()['causationId'];
    }

    public function correlationId(): string|null
    {
        return $this->current()['correlationId'];
    }

    /** @return array{causationId: string|null, correlationId: string|null} */
    private function current(): array
    {
        $count = count($this->stack);

        if ($count === 0) {
            return ['causationId' => null, 'correlationId' => null];
        }

        return $this->stack[$count - 1];
    }
}
