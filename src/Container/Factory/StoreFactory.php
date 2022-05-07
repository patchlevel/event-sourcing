<?php

namespace Patchlevel\EventSourcing\Container\Factory;

use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Store\Store;
use Psr\Container\ContainerInterface;

final class StoreFactory extends Factory
{
    protected function createWithConfig(ContainerInterface $container): Store
    {
        $config = $this->retrieveConfig($container, 'store');

        if ($config['type'] === 'single') {
            return new SingleTableStore(
                $container->get('')
                $this->get($container, 'connection'),
                $this->get($container, 'serializer'),
                $this->get(
                    $container,
                    'aggregate',
                ),
                $config['table_name']
            );
        }

        return new MultiTableStore(
            $this->retrieveDependency(
                $container,
                $configKey,
                'connection',
                ConnectionFactory::class
            ),
            $this->retrieveDependency(
                $container,
                $configKey,
                'serializer',
                SerializerFactory::class
            ),
            $this->retrieveDependency(
                $container,
                $configKey,
                'aggregate',
                AggregateRootRegistryFactory::class
            ),
            $config['table_name']
        );
    }

    protected function defaultConfig(string $configKey): array
    {
        return [
            'type' => 'multi',
            'table_name' => 'eventstore'
        ];
    }
}