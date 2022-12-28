<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container\Factory;

use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\Projection\Projector\SyncProjectorListener;
use Psr\Container\ContainerInterface;

use function array_map;

final class EventBusFactory extends Factory
{
    protected function createWithConfig(ContainerInterface $container): EventBus
    {
        $config = $this->retrieveConfig($container, 'event_bus');

        $listeners = array_map(static fn (string $id): Listener => $container->get($id), $config['listeners']);

        $listeners[] = new SyncProjectorListener(
            $this->retrieveDependency(
                $container,
                'even_sourcing.projector_repository',
                new ProjectorRepositoryFactory()
            )
        );

        return new DefaultEventBus($listeners);
    }

    protected function defaultConfig(): array
    {
        return [
            'listeners' => [],
        ];
    }
}
