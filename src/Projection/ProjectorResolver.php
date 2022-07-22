<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Closure;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projector\Projector;

interface ProjectorResolver
{
    public function resolveCreateMethod(Projector $projector): ?Closure;

    public function resolveDropMethod(Projector $projector): ?Closure;

    public function resolveHandleMethod(Projector $projector, Message $message): ?Closure;
}
