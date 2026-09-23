<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Dbal;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform as BasePostgreSQLPlatform;
use Doctrine\DBAL\ServerVersionProvider;

/**
 * Replaces the doctrine/dbal PostgreSQL platform with {@see PostgreSQLPlatform}.
 * Other platforms are left untouched.
 */
final class PostgreSQLPlatformMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class ($driver) extends AbstractDriverMiddleware {
            public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
            {
                $platform = parent::getDatabasePlatform($versionProvider);

                if ($platform instanceof BasePostgreSQLPlatform) {
                    return new PostgreSQLPlatform();
                }

                return $platform;
            }
        };
    }
}
