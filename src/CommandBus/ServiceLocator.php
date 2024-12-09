<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use Psr\Container\ContainerInterface;
use RuntimeException;

use function array_key_exists;
use function sprintf;

final class ServiceLocator implements ContainerInterface
{
    /** @param array<string, mixed> $services */
    public function __construct(
        private readonly array $services,
    ) {
    }

    public function get(string $id): mixed
    {
        if (!array_key_exists($id, $this->services)) {
            throw new RuntimeException(sprintf('service %s not found', $id));
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}
