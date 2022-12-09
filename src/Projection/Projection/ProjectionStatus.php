<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

enum ProjectionStatus: string
{
    case New = 'new';
    case Booting = 'booting';
    case Active = 'active';
    case Outdated = 'outdated';
    case Error = 'error';
}
