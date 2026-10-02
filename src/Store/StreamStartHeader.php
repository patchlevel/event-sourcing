<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Attribute\Header;

/** @immutable */
#[Header('newStreamStart')]
final class StreamStartHeader
{
}
