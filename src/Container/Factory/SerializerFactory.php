<?php

namespace Patchlevel\EventSourcing\Container\Factory;

use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Psr\Container\ContainerInterface;

final class SerializerFactory extends Factory
{
    protected function createWithConfig(ContainerInterface $container, string $configKey): EventSerializer
    {
        $config = $this->retrieveConfig($container, $configKey, 'event');

        return DefaultEventSerializer::createFromPaths($config['paths']);
    }
}