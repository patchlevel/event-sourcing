<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Debug\Trace;

use function array_values;

/** @experimental */
final class TraceStack
{
    /** @var array<string, Trace> */
    private array $traces = [];

    public function add(Trace $trace): void
    {
        $this->traces[self::key($trace)] = $trace;
    }

    /** @return list<Trace> */
    public function get(): array
    {
        return array_values($this->traces);
    }

    public function remove(Trace $trace): void
    {
        unset($this->traces[self::key($trace)]);
    }

    private static function key(Trace $trace): string
    {
        return $trace->category . '#' . $trace->name;
    }
}
