<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container\Factory;

use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Psr\Container\ContainerInterface;

final class RepositoryManagerFactory extends Factory
{
    protected function createWithConfig(ContainerInterface $container): DefaultRepositoryManager
    {
        return new DefaultRepositoryManager(
            $this->retrieveDependency(
                $container,
                'event_sourcing.aggregate_root_registry',
                new AggregateRootRegistryFactory()
            ),
            $this->retrieveDependency(
                $container,
                'event_sourcing.store',
                new StoreFactory()
            ),
            $this->retrieveDependency(
                $container,
                'event_sourcing.event_bus',
                new EventBusFactory()
            ),
        );
    }
}
