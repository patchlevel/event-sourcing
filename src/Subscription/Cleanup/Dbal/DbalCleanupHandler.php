<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Cleanup\Dbal;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupHandler;

final class DbalCleanupHandler implements CleanupHandler
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(object $task): void
    {
        switch ($task::class) {
            case DropTableTask::class:
                $this->connection->createSchemaManager()->dropTable($task->table);
                break;
            case DropIndexTask::class:
                $this->connection->createSchemaManager()->dropIndex($task->index, $task->table);
                break;
        }
    }

    public function supports(object $task): bool
    {
        return $task instanceof DropTableTask || $task instanceof DropIndexTask;
    }
}
