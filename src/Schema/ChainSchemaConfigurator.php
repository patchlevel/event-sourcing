<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

use Doctrine\DBAL\Schema\Schema;

final class ChainSchemaConfigurator implements SchemaConfigurator
{
    /**
     * @param iterable<SchemaConfigurator> $schemaConfigurator
     */
    public function __construct(
        private readonly iterable $schemaConfigurator
    ) {
    }

    public function configureSchema(Schema $schema): void
    {
        foreach ($this->schemaConfigurator as $schemaConfigurator) {
            $schemaConfigurator->configureSchema($schema);
        }
    }
}
