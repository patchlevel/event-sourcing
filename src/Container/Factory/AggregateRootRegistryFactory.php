<?php

namespace Patchlevel\EventSourcing\Container\Factory;

use Psr\Container\ContainerInterface;

class AggregateRootRegistryFactory extends Factory
{
    protected function createWithConfig(ContainerInterface $container, string $configKey): mixed
    {
        $config = $this->retrieveConfig($container, $configKey, 'aggregate');

        return null;
    }
}