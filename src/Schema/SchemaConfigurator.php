<?php

namespace Patchlevel\EventSourcing\Schema;

use Doctrine\DBAL\Schema\Schema;

interface SchemaConfigurator
{
    public function configureSchema(Schema $schema): void;
}