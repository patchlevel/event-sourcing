<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Closure;
use Patchlevel\EventSourcing\EventBus\Message;

interface ProjectorResolver
{
    public function resolveSetupMethod(object $projector): Closure|null;

    public function resolveTeardownMethod(object $projector): Closure|null;

    public function resolveSubscribeMethod(object $projector, Message $message): Closure|null;

    public function projectorId(object $projector): ProjectorId;
}
