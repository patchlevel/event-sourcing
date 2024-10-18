<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline;

use Patchlevel\EventSourcing\Pipeline\Middleware\ChainMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\Middleware;
use Patchlevel\EventSourcing\Pipeline\Source\Source;
use Patchlevel\EventSourcing\Pipeline\Target\Target;

final class Pipeline
{
    private readonly Middleware $middleware;

    /** @param list<Middleware>|Middleware $middlewares */
    public function __construct(
        private readonly Source $source,
        private readonly Target $target,
        array|Middleware $middlewares = [],
        private readonly int $bufferSize = 1_000,
    ) {
        if (is_array($middlewares)) {
            $this->middleware = new ChainMiddleware($middlewares);
        } else {
            $this->middleware = $middlewares;
        }
    }

    public function run(): void
    {
        $buffer = [];

        foreach ($this->source->load() as $message) {
            $result = ($this->middleware)($message);

            array_push($buffer, ...$result);

            if (count($buffer) >= $this->bufferSize) {
                $this->target->save(...$result);
                $buffer = [];
            }
        }

        if (count($buffer) > 0) {
            $this->target->save(...$buffer);
        }
    }

    public static function execute(
        Source $source,
        Target $target,
        array|Middleware $middlewares = [],
        $bufferSize = 1_000,
    ): void
    {
        $pipeline = new self($source, $target, $middlewares, $bufferSize);
        $pipeline->run();
    }
}