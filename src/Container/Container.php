<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container;

use Psr\Container\ContainerInterface;
use Throwable;

use function array_key_exists;
use function is_callable;
use function sprintf;

/**
 * @phpstan-type ServiceMap array<string, object>
 * @phpstan-type FactoryMap array<string, callable(ContainerInterface): object>
 * @phpstan-type AliasMap array<string, string>
 */
final class Container implements ContainerInterface
{
    /** @var array<string, bool> */
    private array $building = [];

    /**
     * @param ServiceMap $services
     * @param FactoryMap $factories
     * @param AliasMap   $aliases
     */
    public function __construct(
        private array $services = [],
        private array $factories = [],
        private array $aliases = [],
    ) {
    }

    /**
     * @param string|class-string<T> $id
     *
     * @return ($id is class-string<T> ? T : object)
     *
     * @template T of object
     */
    public function get(string $id): mixed
    {
        $id = $this->resolveAlias($id);

        if (array_key_exists($id, $this->services)) {
            return $this->services[$id];
        }

        if (!array_key_exists($id, $this->factories)) {
            throw new ServiceNotFound($id);
        }

        if (array_key_exists($id, $this->building)) {
            throw new ServiceCreationFailed(sprintf('Circular service reference detected for "%s".', $id));
        }

        try {
            $this->building[$id] = true;
            $this->services[$id] = ($this->factories[$id])($this);
        } catch (ServiceNotFound | ServiceCreationFailed $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ServiceCreationFailed(
                sprintf('Could not create service "%s": %s', $id, $exception->getMessage()),
                0,
                $exception,
            );
        } finally {
            unset($this->building[$id]);
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        $id = $this->resolveAlias($id);

        return array_key_exists($id, $this->services) || array_key_exists($id, $this->factories);
    }

    private function resolveAlias(string $id): string
    {
        $resolved = $id;

        while (array_key_exists($resolved, $this->aliases)) {
            $resolved = $this->aliases[$resolved];
        }

        return $resolved;
    }

    public function bind(string $id, object|callable $service): void
    {
        if (is_callable($service)) {
            $this->factories[$id] = $service;

            return;
        }

        $this->services[$id] = $service;
    }

    public function alias(string $alias, string $id): void
    {
        $this->aliases[$alias] = $id;
    }

    public function decorate(string $id, string $decoratorId, callable $factory): void
    {
        $innerId = $this->resolveAlias($id);
        $this->bind(
            $decoratorId,
            static fn (ContainerInterface $container) => $factory($container, $container->get($innerId)),
        );
        $this->alias($id, $decoratorId);
    }
}
