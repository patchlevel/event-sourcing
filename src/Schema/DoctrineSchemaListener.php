<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

final class DoctrineSchemaListener
{
    public function __construct(
        private readonly DoctrineSchemaConfigurator $schemaConfigurator,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $this->schemaConfigurator->configureSchema(
            $event->getSchema(),
            $event->getEntityManager()->getConnection(),
        );
    }
}
