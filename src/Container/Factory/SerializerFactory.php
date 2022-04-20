<?php

namespace Patchlevel\EventSourcing\Container\Factory;

use Patchlevel\EventSourcing\Serializer\JsonSerializer;
use Patchlevel\EventSourcing\Serializer\Serializer;
use Psr\Container\ContainerInterface;

class SerializerFactory extends Factory
{
    protected function createWithConfig(ContainerInterface $container, string $configKey): Serializer
    {
        $config = $this->retrieveConfig($container, $configKey, 'event');

        return JsonSerializer::createDefault($config['paths']);
    }
}