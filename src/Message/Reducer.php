<?php

namespace Patchlevel\EventSourcing\Message;

use Closure;
use Patchlevel\EventSourcing\Message\Translator\Translator;

/**
 * @template STATE of array<array-key, mixed>
 * @template OUT of array<array-key, mixed> = STATE
 */
final class Reducer
{
    /**
     * @var STATE
     */
    private array $initState = [];

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
     * @var list<Translator>
     */
    private array $translators = [];

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

    public function translator(Translator ...$translators): self
    {
        foreach ($translators as $translator) {
            $this->translators[] = $translator;
        }

        return $this;
    }

    /**
     * @param iterable<Message> $messages
     *
     * @return OUT
     */
    public function reduce(iterable $messages): array
    {
        if ($this->translators !== []) {
            $messages = new Pipeline($messages, $this->translators);
        }

        $state = $this->initState;

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