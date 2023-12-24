<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Closure;
use Patchlevel\EventSourcing\EventBus\Message;

interface ProjectorResolver
{
    public function resolveCreateMethod(Projector $projector): Closure|null;

    public function resolveDropMethod(Projector $projector): Closure|null;

    public function resolveSubscribeMethod(Projector $projector, Message $message): Closure|null;
}
