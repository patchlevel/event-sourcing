<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container\Factory;

use Psr\Container\ContainerInterface;
use RuntimeException;

use function array_replace_recursive;
use function is_array;

abstract class Factory
{
    final public function __construct()
    {
    }

    public function __invoke(ContainerInterface $container): mixed
    {
        return $this->createWithConfig($container);
    }

    abstract protected function createWithConfig(ContainerInterface $container): mixed;

    /**
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [];
    }

    protected function retrieveConfig(ContainerInterface $container, string $section): array
    {
        $applicationConfig = $container->has('config') ? $container->get('config') : [];

        if (!is_array($applicationConfig)) {
            throw new RuntimeException('wrong config');
        }

        $sectionConfig = $applicationConfig['event_sourcing'][$section] ?? [];

        if (!is_array($sectionConfig)) {
            throw new RuntimeException('wrong config');
        }

        return array_replace_recursive($this->defaultConfig(), $sectionConfig);
    }

    /**
     * @param string|class-string<T> $service
     *
     * @return object|T
     *
     * @template T of object
     */
    protected function retrieveDependency(
        ContainerInterface $container,
        string $service,
        Factory $factory,
    ): object {
        if ($container->has($service)) {
            return $container->get($service);
        }

        return (new $factory())($container);
    }
}
