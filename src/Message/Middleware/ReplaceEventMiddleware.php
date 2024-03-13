<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Middleware;

use Patchlevel\EventSourcing\Message\Message;

/** @template T of object */
final class ReplaceEventMiddleware implements Middleware
{
    /** @var callable(T $event):object */
    private $callable;

    /**
     * @param class-string<T> $class
     * @param callable(T      $event):object $callable
     */
    public function __construct(
        private readonly string $class,
        callable $callable,
    ) {
        $this->callable = $callable;
    }

    /** @return list<Message> */
    public function __invoke(Message $message): array
    {
        $event = $message->event();

        if (!$event instanceof $this->class) {
            return [$message];
        }

        $callable = $this->callable;
        $newEvent = $callable($event);

        return [Message::createWithHeaders($newEvent, $message->headers())];
    }
}
