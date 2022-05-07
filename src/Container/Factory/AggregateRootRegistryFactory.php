<?php

namespace Patchlevel\EventSourcing\Container\Factory;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Psr\Container\ContainerInterface;

final class AggregateRootRegistryFactory extends Factory
{
    protected function createWithConfig(ContainerInterface $container): AggregateRootRegistry
    {
        $config = $this->retrieveConfig($container, 'aggregate');

        return (new AttributeAggregateRootRegistryFactory())->create($config['paths']);
    }
}