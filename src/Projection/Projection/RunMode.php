<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

enum RunMode: string
{
    case FromBeginning = 'from_beginning';
    case FromNow = 'from_now';
    case Once = 'once';
}
