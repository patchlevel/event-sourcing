<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
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

        $connection = DriverManager::getConnection($connectionParams);

        if ($connection->getDriver() instanceof AbstractSQLiteDriver) {
            return $connection;
        }

        $tempConnection = (new DoctrineHelper())->copyConnectionWithoutDatabase($connection);

        $schemaManager = $tempConnection->createSchemaManager();
        $databases = $schemaManager->listDatabases();

        if (in_array($dbName, $databases, true)) {
            $schemaManager->dropDatabase($dbName);
        }

        $schemaManager->createDatabase($dbName);
        $tempConnection->close();

        return $connection;
    }
}
