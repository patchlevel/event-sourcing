<?php

namespace Patchlevel\EventSourcing\Container\Factory;

use Psr\Container\ContainerInterface;

abstract class Factory
{
    private string $configKey;

    final public function __construct(string $configKey = 'event_sourcing')
    {
        $this->configKey = $configKey;
    }

    public function __invoke(ContainerInterface $container): mixed
    {
        return $this->createWithConfig($container, $this->configKey);
    }

    abstract protected function createWithConfig(ContainerInterface $container, string $configKey): mixed;

    /**
     * @param string $configKey
     * @return array<string, mixed>
     */
    protected function defaultConfig(string $configKey): array
    {
        return [];
    }

    protected function retrieveConfig(ContainerInterface $container, string $configKey, string $section): array
    {
        $applicationConfig = $container->has('config') ? $container->get('config') : [];

        if (!is_array($applicationConfig)) {
            throw new \RuntimeException('wrong config');
        }

        $sectionConfig = $applicationConfig['doctrine'][$section] ?? [];

        if (!is_array($sectionConfig)) {
            throw new \RuntimeException('wrong config');
        }

        if (array_key_exists($configKey, $sectionConfig)) {
            return array_replace_recursive($this->defaultConfig($configKey), $sectionConfig[$configKey]);
        }

        return $this->defaultConfig($configKey);
    }

    /**
     * @param class-string $factoryClassName
     */
    protected function retrieveDependency(
        ContainerInterface $container,
        string $configKey,
        string $section,
        string $factoryClassName
    ): mixed {
        $containerKey = sprintf('doctrine.%s.%s', $section, $configKey);

        if ($container->has($containerKey)) {
            return $container->get($containerKey);
        }

        return (new $factoryClassName($configKey))->__invoke($container);
    }
}