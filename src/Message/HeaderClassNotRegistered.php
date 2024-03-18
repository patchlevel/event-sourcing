<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message;

use RuntimeException;

use function sprintf;

final class HeaderClassNotRegistered extends RuntimeException
{
    public function __construct(string $headerClass)
    {
        parent::__construct(
            sprintf(
                'Header class "%s" is not registered',
                $headerClass,
            ),
        );
    }
}
