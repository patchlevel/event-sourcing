<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

use function class_exists;

final class DoctrineSchemaSubscriber implements EventSubscriber
{
    public function __construct(
        private readonly SchemaConfigurator $schemaConfigurator
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $this->schemaConfigurator->configureSchema(
            $event->getSchema(),
            $event->getEntityManager()->getConnection()
        );
    }

    /**
     * @return list<string>
     */
    public function getSubscribedEvents(): array
    {
        $subscribedEvents = [];

        if (class_exists(ToolEvents::class)) {
            $subscribedEvents[] = ToolEvents::postGenerateSchema;
        }

        return $subscribedEvents;
    }
}
