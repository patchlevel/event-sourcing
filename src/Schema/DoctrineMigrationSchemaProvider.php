<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Provider\SchemaProvider;

final class DoctrineMigrationSchemaProvider implements SchemaProvider
{
    public function __construct(
        private readonly DoctrineSchemaDirector $doctrineSchemaDirector
    ) {
    }

    public function createSchema(): Schema
    {
        return $this->doctrineSchemaDirector->schema();
    }
}
