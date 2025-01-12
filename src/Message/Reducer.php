<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message;

use Closure;
use RuntimeException;

/**
 * @template STATE of array<array-key, mixed>
 * @template OUT of array<array-key, mixed> = STATE
 */
final class Reducer
{
    /** @var STATE */
    private array $initState = [];

    /** @var array<class-string, list<Closure(Message, STATE): STATE>> */
    private array $handlers = [];

    /** @var list<Closure(Message, STATE): STATE> */
    private array $anyHandlers = [];

    /** @var (Closure(STATE): OUT)|null */
    private Closure|null $finalizeHandler = null;

    /** @var iterable<Message>|null */
    private iterable|null $messages = null;

    /**
     * @param STATE $initState
     *
     * @return $this
     */
    public function initState(array $initState): self
    {
        $this->initState = $initState;

        return $this;
    }

    /**
     * @param class-string<T1>                   $event
     * @param Closure(Message<T1>, STATE): STATE $closure
     *
     * @return $this
     *
     * @template T1 of object
     */
    public function when(string $event, Closure $closure): self
    {
        if (!isset($this->handlers[$event])) {
            $this->handlers[$event] = [];
        }

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

    /** @param iterable<Message> $messages */
    public function messages(iterable $messages): self
    {
        $this->messages = $messages;

        return $this;
    }

    /**
     * @param iterable<Message>|null $messages
     *
     * @return OUT|STATE
     * @psalm-return (OUT is STATE ? STATE : OUT)
     */
    public function reduce(iterable|null $messages = null): array
    {
        $state = $this->initState;

        if ($messages === null) {
            $messages = $this->messages;

            if ($messages === null) {
                throw new RuntimeException('no messages given');
            }
        }

        foreach ($messages as $message) {
            $event = $message->event();

            if (isset($this->handlers[$event::class])) {
                foreach ($this->handlers[$event::class] as $handler) {
                    $state = $handler($message, $state);
                }
            }

            foreach ($this->anyHandlers as $handler) {
                $state = $handler($message, $state);
            }
        }

        if ($this->finalizeHandler !== null) {
            $state = ($this->finalizeHandler)($state);
        }

        return $state;
    }
}
