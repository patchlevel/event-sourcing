<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Cleanup\Dbal;

use RuntimeException;

use function sprintf;

final class ConnectionNameNotSupported extends RuntimeException
{
    public function __construct(string $connectionName)
    {
        parent::__construct(
            sprintf(
                'Connection name "%s" is not supported. Only a single connection is available. Please use the connection registry.',
                $connectionName,
            ),
        );
    }
}
