<?php

declare(strict_types=1);

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
                $this->retrieveDependency(
                    $container,
                    ConnectionFactory::SERVICE_NAME,
                    new ConnectionFactory()
                ),
                $this->retrieveDependency(
                    $container,
                    'event_sourcing.event_serializer',
                    new EventSerializerFactory()
                ),
                $this->retrieveDependency(
                    $container,
                    'event_sourcing.aggregate_root_factory',
                    new AggregateRootRegistryFactory()
                ),
                $config['table_name']
            );
        }

        return new MultiTableStore(
            $this->retrieveDependency(
                $container,
                ConnectionFactory::class,
                new ConnectionFactory()
            ),
            $this->retrieveDependency(
                $container,
                'event_sourcing.event_serializer',
                new EventSerializerFactory()
            ),
            $this->retrieveDependency(
                $container,
                'event_sourcing.aggregate_root_factory',
                new AggregateRootRegistryFactory()
            ),
            $config['table_name']
        );
    }

    protected function defaultConfig(): array
    {
        return [
            'type' => 'multi',
            'table_name' => 'eventstore',
        ];
    }
}
