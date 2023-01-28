<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\EventBus\Message;

final class ChainMiddleware implements Middleware
{
    /**
     * @param iterable<Middleware> $middlewares
     */
    public function __construct(
        private readonly iterable $middlewares
    ) {
    }

    /**
     * @return list<Message>
     */
    public function __invoke(Message $message): array
    {
        $messages = [$message];

        foreach ($this->middlewares as $middleware) {
            $messages = $this->processMiddleware($middleware, $messages);
        }

        return $messages;
    }

    /**
     * @param list<Message> $messages
     *
     * @return list<Message>
     */
    private function processMiddleware(Middleware $middleware, array $messages): array
    {
        $result = [];

        foreach ($messages as $message) {
            $result += $middleware($message);
        }

        return array_values($result);
    }
}
