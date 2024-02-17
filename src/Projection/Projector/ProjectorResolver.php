<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Closure;
use Patchlevel\EventSourcing\EventBus\Message;

interface ProjectorResolver
{
    public function resolveSetupMethod(object $projector): Closure|null;

    public function resolveTeardownMethod(object $projector): Closure|null;

    /** @return iterable<Closure> */
    public function resolveSubscribeMethods(object $projector, Message $message): iterable;

    public function projectorId(object $projector): string;
}
