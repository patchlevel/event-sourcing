<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use RuntimeException;

use function sprintf;

class HeaderNotFound extends RuntimeException
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf('message header "%s" is not defined', $name));
    }
}
