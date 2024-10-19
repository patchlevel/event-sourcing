<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\Middleware;
use Patchlevel\EventSourcing\Pipeline\Target\Target;

use function count;

final class Pipeline
{
    private readonly array $middlewares;

    /** @param list<Middleware>|Middleware $middlewares */
    public function __construct(
        private readonly Target $target,
        array|Middleware $middlewares = [],
        private readonly float|int $bufferSize = 1_000,
    ) {
        if ($middlewares instanceof Middleware) {
            $this->middlewares = [$middlewares];
        } else {
            $this->middlewares = $middlewares;
        }
    }

    /** @param iterable<Message> $messages */
    public function run(iterable $messages): void
    {
        $stream = new Pipe(
            $messages,
            $this->middlewares,
        );

        $buffer = [];

        foreach ($stream as $message) {
            $buffer[] = $message;

            if (count($buffer) < $this->bufferSize) {
                continue;
            }

            $this->target->save(...$buffer);
            $buffer = [];
        }

        if ($buffer === []) {
            return;
        }

        $this->target->save(...$buffer);
    }
}
