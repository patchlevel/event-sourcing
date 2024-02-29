<?php

namespace Patchlevel\EventSourcing\Projection\Projector;

use Closure;
use Patchlevel\EventSourcing\Projection\Projection\RunMode;

interface ProjectorAccessor
{
    public function id(): string;

    public function group(): string;

    public function runMode(): RunMode;
    public function setupMethod(): Closure|null;

    public function teardownMethod(): Closure|null;

    /**
     * @param class-string $eventClass
     *
     * @return iterable<Closure>
     */
    public function subscribeMethods(string $eventClass): iterable;
}