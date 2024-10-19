<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline;

use Generator;
use IteratorAggregate;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\ChainMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\Middleware;
use Traversable;

/** @implements IteratorAggregate<Message> */
final class Pipe implements IteratorAggregate
{
    /**
     * @param iterable<Message> $messages
     * @param list<Middleware>  $middlewares
     */
    public function __construct(
        private readonly iterable $messages,
        private readonly array $middlewares = [],
    ) {
    }

    public function appendMiddleware(Middleware $middleware): self
    {
        return new self(
            $this->messages,
            [...$this->middlewares, $middleware],
        );
    }

    public function prependMiddleware(Middleware $middleware): self
    {
        return new self(
            $this->messages,
            [$middleware, ...$this->middlewares],
        );
    }

    /** @return Traversable<Message> */
    public function getIterator(): Traversable
    {
        return $this->createGenerator(
            $this->messages,
            new ChainMiddleware($this->middlewares),
        );
    }

    /**
     * @param iterable<Message> $messages
     *
     * @return Generator<Message>
     */
    private function createGenerator(iterable $messages, Middleware $middleware): Generator
    {
        foreach ($messages as $message) {
            $result = $middleware($message);

            foreach ($result as $m) {
                yield $m;
            }
        }
    }
}
