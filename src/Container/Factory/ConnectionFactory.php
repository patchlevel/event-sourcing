<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container\Factory;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Psr\Container\ContainerInterface;

final class ConnectionFactory extends Factory
{
    public const SERVICE_NAME = 'event_sourcing.connection';

    protected function createWithConfig(ContainerInterface $container): Connection
    {
        $config = $this->retrieveConfig($container, 'connection');

        return DriverManager::getConnection([
            'url' => $config['url'],
        ]);
    }
}
