<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

enum ProjectorStatus: string
{
    case New = 'new';
    case Booting = 'booting';
    case Active = 'active';
    case Outdated = 'outdated';
    case Error = 'error';
}
