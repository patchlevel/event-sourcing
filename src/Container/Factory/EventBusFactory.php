<?php

namespace Patchlevel\EventSourcing\Container\Factory;

use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Psr\Container\ContainerInterface;

final class EventBusFactory extends Factory
{
    protected function createWithConfig(ContainerInterface $container): EventBus
    {
        $config = $this->retrieveConfig($container, 'event_bus');

        return new DefaultEventBus();
    }
}