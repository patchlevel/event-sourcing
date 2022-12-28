<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container\Factory;

use Patchlevel\EventSourcing\Projection\Projector\InMemoryProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector\Projector;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorRepository;
use Psr\Container\ContainerInterface;

use function array_map;

final class ProjectorRepositoryFactory extends Factory
{
    protected function createWithConfig(ContainerInterface $container): ProjectorRepository
    {
        $config = $this->retrieveConfig($container, 'projectors');

        return new InMemoryProjectorRepository(
            array_map(static fn (string $id): Projector => $container->get($id), $config)
        );
    }
}
