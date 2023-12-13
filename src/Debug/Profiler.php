<?php

namespace Patchlevel\EventSourcing\Debug;

interface Profiler
{
    /**
     * @param \Closure():T $closure
     *
     * @return T
     *
     * @template T of mixed
     */
    public function profile(string $name, \Closure $closure, $context = []): mixed;
}