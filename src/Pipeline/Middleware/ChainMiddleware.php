<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Message\Message;

use function array_values;

final class ChainMiddleware implements Middleware
{
    /** @param iterable<Middleware> $translators */
    public function __construct(
        private readonly iterable $translators,
    ) {
    }

    /** @return list<Message> */
    public function __invoke(Message $message): array
    {
        $messages = [$message];

        foreach ($this->translators as $middleware) {
            $messages = $this->process($middleware, $messages);
        }

        return $messages;
    }

    /**
     * @param list<Message> $messages
     *
     * @return list<Message>
     */
    private function process(Middleware $translator, array $messages): array
    {
        $result = [];

        foreach ($messages as $message) {
            $result += $translator($message);
        }

        return array_values($result);
    }
}
