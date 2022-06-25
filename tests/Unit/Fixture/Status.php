<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

enum Status: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Closed = 'closed';
}
