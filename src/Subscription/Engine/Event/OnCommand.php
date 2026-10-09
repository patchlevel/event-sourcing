<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Event;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;

final class OnCommand
{
    public function __construct(
        public readonly Command $command,
    ) {
    }
}
