<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\ChainMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\Middleware;
use Patchlevel\EventSourcing\Pipeline\Target\Target;

use function array_push;
use function count;
use function is_array;

final class Pipeline
{
    private readonly Middleware $middleware;

    /** @param list<Middleware>|Middleware $middlewares */
    public function __construct(
        private readonly Target $target,
        array|Middleware $middlewares = [],
        private readonly float|int $bufferSize = 0,
    ) {
        if (is_array($middlewares)) {
            $this->middleware = new ChainMiddleware($middlewares);
        } else {
            $this->middleware = $middlewares;
        }
    }

    /** @param iterable<Message> $messages */
    public function run(iterable $messages): void
    {
        $buffer = [];

        foreach ($messages as $message) {
            $result = ($this->middleware)($message);

            array_push($buffer, ...$result);

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
