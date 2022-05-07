<?php

namespace Patchlevel\EventSourcing\Container\Factory;

use Psr\Container\ContainerInterface;

abstract class Factory
{
    private const PACKAGE_NAME = 'event_sourcing';

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
            throw new \RuntimeException('wrong config');
        }

        $sectionConfig = $applicationConfig[self::PACKAGE_NAME][$section] ?? [];

        if (!is_array($sectionConfig)) {
            throw new \RuntimeException('wrong config');
        }

        return array_replace_recursive($this->defaultConfig(), $sectionConfig);
    }

    protected function get(ContainerInterface $container, string $name): mixed
    {
        $key = sprintf('%s.%s', self::PACKAGE_NAME, $name);

        return $container->get($key);
    }
}