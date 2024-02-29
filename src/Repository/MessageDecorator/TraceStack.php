<?php

namespace Patchlevel\EventSourcing\Repository\MessageDecorator;
final class TraceStack
{
    /**
     * @var array<string, Trace>
     */
    private array $traces = [];

    public function add(Trace $trace): void
    {
        $this->traces[$trace->name] = $trace;
    }

    /**
     * @return iterable<Trace>
     */
    public function get(): array
    {
        return array_values($this->traces);
    }

    public function remove(Trace $trace): void
    {
        unset($this->traces[$trace->name]);
    }

    private static function key(Trace $trace): string
    {
        return $trace->category . '#' . $trace->name;
    }
}