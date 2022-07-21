<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Worker;

use InvalidArgumentException;

use function sprintf;

final class InvalidFormat extends InvalidArgumentException
{
    public function __construct(string $message)
    {
        parent::__construct(sprintf('Invalid byte format received (got: "%s"). The format must consist of a number and a unit. The following units are allowed: B, KB, MB, GB', $message));
    }
}
