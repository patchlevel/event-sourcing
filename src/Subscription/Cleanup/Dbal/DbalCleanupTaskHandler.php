<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Cleanup\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupTaskHandler;
use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupTaskNotSupported;

use function strtolower;

final class DbalCleanupTaskHandler implements CleanupTaskHandler
{
    public function __construct(
        private readonly Connection|ConnectionRegistry $connection,
    ) {
    }

    public function __invoke(object $task): void
    {
        if ($task instanceof DropTableTask) {
            $schemaManager = $this->connection($task->connectionName)->createSchemaManager();
            if ($schemaManager->tablesExist([$task->table])) {
                $schemaManager->dropTable($task->table);
            }

            return;
        }

        if ($task instanceof DropIndexTask) {
            $schemaManager = $this->connection($task->connectionName)->createSchemaManager();

            if (!$schemaManager->tablesExist([$task->table])) {
                return;
            }

            foreach ($schemaManager->introspectTableIndexesByUnquotedName($task->table) as $index) {
                if (strtolower($index->getObjectName()->toString()) === strtolower($task->index)) {
                    $schemaManager->dropIndex($task->index, $task->table);
                    break;
                }
            }

            return;
        }

        throw new CleanupTaskNotSupported($task, self::class);
    }

    public function supports(object $task): bool
    {
        return $task instanceof DropTableTask || $task instanceof DropIndexTask;
    }

    private function connection(string|null $connectionName): Connection
    {
        if ($this->connection instanceof ConnectionRegistry) {
            $connection = $this->connection->getConnection($connectionName);

            if (!$connection instanceof Connection) {
                throw new UnexpectedConnectionType($connectionName, Connection::class, $connection::class);
            }

            return $connection;
        }

        if ($connectionName === null) {
            return $this->connection;
        }

        throw new ConnectionNameNotSupported($connectionName);
    }
}
