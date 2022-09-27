<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Provider\SchemaProvider;
use Patchlevel\EventSourcing\Store\DoctrineStore;

/**
 * @deprecated use DoctrineMigrationSchemaProvider
 */
final class MigrationSchemaProvider implements SchemaProvider
{
    public function __construct(
        private readonly DoctrineStore $doctrineStore
    ) {
    }

    public function createSchema(): Schema
    {
        return $this->doctrineStore->schema();
    }
}
