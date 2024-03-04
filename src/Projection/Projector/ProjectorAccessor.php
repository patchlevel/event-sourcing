<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Closure;
use Patchlevel\EventSourcing\EventBus\Message;
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
     * @return list<Closure(Message):void>
     */
    public function subscribeMethods(string $eventClass): array;
}
