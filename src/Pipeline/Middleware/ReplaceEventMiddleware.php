<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\EventBus\Message;

/**
 * @template T of object
 */
final class ReplaceEventMiddleware implements Middleware
{
    /** @var class-string<T> */
    private string $class;

    /** @var callable(T $event):object */
    private $callable;

    /**
     * @param class-string<T> $class
     * @param callable(T      $event):object $callable
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

        return [Message::createWithHeaders($newEvent, $message->headers())];
    }
}
