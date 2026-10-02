<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository\MessageDecorator;

use Patchlevel\EventSourcing\Attribute\Header;

/** @immutable */
#[Header('newStreamStart')]
final class StreamStartHeader
{
}
