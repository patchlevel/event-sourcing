<?php

namespace Patchlevel\EventSourcing\Container\Factory;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Psr\Container\ContainerInterface;

class ConnectionFactory extends Factory
{
    protected function createWithConfig(ContainerInterface $container, string $configKey): Connection
    {
        $config = $this->retrieveConfig($container, $configKey, 'connection');

        return DriverManager::getConnection([
            'url' => $config['url'],
        ]);
    }
}