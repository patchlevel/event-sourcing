<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;

final class ChainDoctrineSchemaConfigurator implements DoctrineSchemaConfigurator
{
    /** @param iterable<DoctrineSchemaConfigurator> $schemaConfigurator */
    public function __construct(
        private readonly iterable $schemaConfigurator,
    ) {
    }

    public function configureSchema(Schema $schema, Connection $connection): void
    {
        foreach ($this->schemaConfigurator as $schemaConfigurator) {
            $schemaConfigurator->configureSchema($schema, $connection);
        }
    }
}
