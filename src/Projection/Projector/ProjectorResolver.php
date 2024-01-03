<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Closure;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;

interface ProjectorResolver
{
    public function resolveSetupMethod(object $projector): Closure|null;

    public function resolveTeardownMethod(object $projector): Closure|null;

    public function resolveSubscribeMethod(object $projector, Message $message): Closure|null;

    public function projectionId(object $projector): ProjectionId;
}
