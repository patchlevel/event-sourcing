<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Tools\DsnParser;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use Patchlevel\EventSourcing\Store\Dbal\PostgreSQLPlatformMiddleware;
use RuntimeException;

use function getenv;
use function in_array;
use function is_string;

final class DbalManager
{
    public const DEFAULT_DB_NAME = 'eventstore';

    public static function createConnection(string $dbName = self::DEFAULT_DB_NAME): Connection
    {
        $dbUrl = getenv('DB_URL');

        if (!is_string($dbUrl)) {
            throw new RuntimeException('missing DB_URL env');
        }

        $connectionParams = (new DsnParser())->parse($dbUrl);

        if ($dbName !== self::DEFAULT_DB_NAME) {
            $connectionParams['dbname'] = $dbName;
        }

        $connection = DriverManager::getConnection(
            $connectionParams,
            (new Configuration())->setMiddlewares([new PostgreSQLPlatformMiddleware()]),
        );

        if (in_array($connectionParams['driver'] ?? null, ['pdo_sqlite', 'sqlite3'], true)) {
            return $connection;
        }

        $tempConnection = (new DoctrineHelper())->copyConnectionWithoutDatabase($connection);

        $schemaManager = $tempConnection->createSchemaManager();
        $databases = $schemaManager->listDatabases();

        if (in_array($dbName, $databases, true)) {
            if ($tempConnection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $tempConnection->executeStatement("
                    SELECT pg_terminate_backend(pid)
                    FROM pg_stat_activity
                    WHERE datname = '{$dbName}';
                ");
            }

            $schemaManager->dropDatabase($dbName);
        }

        $schemaManager->createDatabase($dbName);
        $tempConnection->close();

        return $connection;
    }
}
