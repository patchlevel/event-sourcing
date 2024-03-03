<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;

interface DoctrineSchemaConfigurator
{
    public function configureSchema(Schema $schema, Connection $connection): void;
}
