<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription;

enum Status: string
{
    case New = 'new';
    case Booting = 'booting';
    case Active = 'active';
    case Paused = 'paused';
    case Finished = 'finished';
    case Outdated = 'outdated';
    case Error = 'error';
}
