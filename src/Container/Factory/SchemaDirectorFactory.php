<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container\Factory;

use Patchlevel\EventSourcing\Schema\ChainSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use Psr\Container\ContainerInterface;

final class SchemaDirectorFactory extends Factory
{
    protected function createWithConfig(ContainerInterface $container): SchemaDirector
    {
        return new DoctrineSchemaDirector(
            $this->retrieveDependency(
                $container,
                'event_sourcing.connection',
                new ConnectionFactory()
            ),
            new ChainSchemaConfigurator([
                $this->retrieveDependency(
                    $container,
                    'event_sourcing.store',
                    new StoreFactory()
                ),
            ]),
        );
    }
}
