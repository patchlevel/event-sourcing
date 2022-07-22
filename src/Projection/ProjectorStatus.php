<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

enum ProjectorStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Stale = 'stale';
    case Error = 'error';
}
