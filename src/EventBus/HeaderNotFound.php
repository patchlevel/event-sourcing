<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use function sprintf;

final class HeaderNotFound extends EventBusException
{
    public function __construct(
        public readonly string $name,
    ) {
        parent::__construct(sprintf('message header "%s" is not defined', $name));
    }
}
