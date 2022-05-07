<?php

namespace Patchlevel\EventSourcing\Container\Factory;

use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Psr\Container\ContainerInterface;

final class RepositoryManagerFactory extends Factory
{
    protected function createWithConfig(ContainerInterface $container, string $configKey): DefaultRepositoryManager
    {
        return new DefaultRepositoryManager(
            $container->get('aggregate_root_registry'),
            $container->get('aggregate_root_registry'),
            $container->get('aggregate_root_registry'),
            $this->retrieveDependency(
                $container,
                $configKey,
                'aggregate_root_registry',
                AggregateRootRegistryFactory::class
            ),
            $this->retrieveDependency(
                $container,
                $configKey,
                'store',
                StoreFactory::class
            ),
            $this->retrieveDependency(
                $container,
                $configKey,
                'event_bus',
                EventBusFactory::class
            ),
        );
    }
}