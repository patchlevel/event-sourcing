<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\EventBus\Message;

/**
 * @template T of AggregateChanged
 */
final class ReplaceEventMiddleware implements Middleware
{
    /** @var class-string<T> */
    private string $class;

    /** @var callable(T $event):AggregateChanged<array<string, mixed>> */
    private $callable;

    /**
     * @param class-string<T> $class
     * @param callable(T      $event):AggregateChanged<array<string, mixed>> $callable
     */
    public function __construct(string $class, callable $callable)
    {
        $this->class = $class;
        $this->callable = $callable;
    }

    /**
     * @return list<Message>
     */
    public function __invoke(Message $message): array
    {
        $event = $message->event();

        if (!$event instanceof $this->class) {
            return [$message];
        }

        $callable = $this->callable;
        $newEvent = $callable($event);

        return [
            new Message(
                $message->aggregateClass(),
                $message->aggregateId(),
                $message->playhead(),
                $newEvent,
                $message->recordedOn()
            )
        ];
    }
}
