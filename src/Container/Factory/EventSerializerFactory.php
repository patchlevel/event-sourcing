<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container\Factory;

use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Psr\Container\ContainerInterface;

final class EventSerializerFactory extends Factory
{
    protected function createWithConfig(ContainerInterface $container): EventSerializer
    {
        $config = $this->retrieveConfig($container, 'event');

        return DefaultEventSerializer::createFromPaths($config['paths']);
    }

    protected function defaultConfig(): array
    {
        return [
            'paths' => [],
        ];
    }
}
