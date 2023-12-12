<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Lock;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Patchlevel\EventSourcing\Schema\SchemaConfigurator;
use Symfony\Component\Lock\Store\DoctrineDbalStore;

final class DoctrineDbalStoreSchemaAdapter implements SchemaConfigurator
{
    public function __construct(
        private readonly DoctrineDbalStore $doctrineDbalStore,
    ) {
    }

    public function configureSchema(Schema $schema, Connection $connection): void
    {
        $this->doctrineDbalStore->configureSchema($schema);
    }
}
