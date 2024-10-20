<?php

namespace Patchlevel\EventSourcing\Pipeline;

use Closure;
use Doctrine\Migrations\Version\State;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\ClosureMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\Middleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;

/**
 * @template STATE of array<array-key, mixed>
 * @template OUT of array<array-key, mixed> = STATE
 */
final class StateProcessor
{
    /**
     * @var STATE
     */
    private array $state = [];

    /**
     * @var array<class-string, list<Closure(Message, STATE): STATE|null>>
     */
    private array $handlers = [];

    /**
     * @var list<Closure(Message, STATE): STATE>
     */
    private array $anyHandlers = [];

    /**
     * @var (Closure(STATE): OUT)|null
     */
    private Closure|null $finalizeHandler = null;

    /**
     * @var list<Middleware>
     */
    private array $middlewares = [];

    /**
     * @param STATE $state
     *
     * @return $this
     */
    public function initState(array $state): self
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @template T1 of object
     *
     * @param class-string<T1> $event
     * @param Closure(Message<T1>, STATE): STATE $closure
     *
     * @return $this
     */
    public function when(string $event, Closure $closure): self
    {
        $this->handlers[$event][] = $closure;

        return $this;
    }

    /**
     * @param Closure(Message, STATE): STATE $closure
     *
     * @return $this
     */
    public function any(Closure $closure): self
    {
        $this->anyHandlers[] = $closure;

        return $this;
    }

    /**
     * @param array<class-string, Closure(Message, STATE): STATE> $map
     *
     * @return $this
     */
    public function match(array $map): self
    {
        foreach ($map as $event => $closure) {
            $this->when($event, $closure);
        }

        return $this;
    }

    /**
     * @param Closure(STATE): OUT $closure
     *
     * @return $this
     */
    public function finalize(Closure $closure): self
    {
        $this->finalizeHandler = $closure;

        return $this;
    }

    public function middleware(Middleware ...$middlewares): self
    {
        foreach ($middlewares as $middleware) {
            $this->middlewares[] = $middleware;
        }

        return $this;
    }

    /**
     * @param iterable<Message> $messages
     *
     * @return OUT
     */
    public function process(iterable $messages): array
    {
        if ($this->middlewares !== []) {
            $messages = new Pipe($messages, $this->middlewares);
        }

        foreach ($messages as $message) {
            $event = $message->event();

            if (isset($this->handlers[$event::class])) {
                foreach ($this->handlers[$event::class] as $handler) {
                    $this->state = $handler($message, $this->state);
                }
            }

            foreach ($this->anyHandlers as $handler) {
                $this->state = $handler($message, $this->state);
            }
        }

        if ($this->finalizeHandler !== null) {
            $this->state = ($this->finalizeHandler)($this->state);
        }

        return $this->state;
    }
}

/**
 * @var StateProcessor<array{string, true}, list<string>> $state
 */
$state = (new StateProcessor())
    ->when(ProfileCreated::class, function (Message $message, array $state): array {
        $event = $message->event();

        $state[$event->email->toString()] = true;

        return $state;
    })
    ->finalize(function (array $state): array {
        return array_keys($state);
    })
    ->middleware(new ClosureMiddleware(static function (Message $message): array {
        return [$message];
    }))
    ->process([]);


$state = (new StateProcessor())
    ->initState(['foo' => 'bar'])
    ->any(function (Message $message, array $state): array {
        return $state;
    })
    ->finalize(function (array $state): array {
        return $state;
    })
    ->process([]);
