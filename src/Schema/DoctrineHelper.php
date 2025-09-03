<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Throwable;

use function bin2hex;
use function random_bytes;
use function sprintf;

final class DoctrineHelper
{
    public static function sameDatabase(Connection $connectionA, Connection $connectionB): bool
    {
        if ($connectionA === $connectionB) {
            return true;
        }

        $checkTable = 'same_db_check_' . bin2hex(random_bytes(7));
        $connectionA->executeStatement(sprintf('CREATE TABLE %s (id INTEGER NOT NULL)', $checkTable));

        try {
            $connectionB->executeStatement(sprintf('DROP TABLE %s', $checkTable));
        } catch (Throwable) {
            // ignore
        }

        try {
            $connectionA->executeStatement(sprintf('DROP TABLE %s', $checkTable));

            return false;
        } catch (TableNotFoundException) {
            return true;
        }
    }
}
