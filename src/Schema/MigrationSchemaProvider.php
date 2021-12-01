<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Provider\SchemaProvider;
use Patchlevel\EventSourcing\Store\DoctrineStore;

final class MigrationSchemaProvider implements SchemaProvider
{
    private DoctrineStore $doctrineStore;

    public function __construct(DoctrineStore $doctrineStore)
    {
        $this->doctrineStore = $doctrineStore;
    }

    public function createSchema(): Schema
    {
        return $this->doctrineStore->schema();
    }
}
