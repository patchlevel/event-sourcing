<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use Psr\Container\ContainerInterface;

use function array_key_exists;

final class ServiceLocator implements ContainerInterface
{
    /** @param array<string, mixed> $services */
    public function __construct(
        private readonly array $services = [],
    ) {
    }

    public function get(string $id): mixed
    {
        if (!array_key_exists($id, $this->services)) {
            throw new ServiceNotFound($id);
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}
