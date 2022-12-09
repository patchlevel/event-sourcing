<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Closure;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projection;

interface ProjectorResolver
{
    public function resolveCreateMethod(Projection $projector): ?Closure;

    public function resolveDropMethod(Projection $projector): ?Closure;

    public function resolveHandleMethod(Projection $projector, Message $message): ?Closure;
}
