<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message;

use RuntimeException;

use function sprintf;

final class HeaderNameNotRegistered extends RuntimeException
{
    public function __construct(string $name)
    {
        parent::__construct(
            sprintf(
                'Header name "%s" is not registered',
                $name,
            ),
        );
    }
}
