<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Container\Fixture;

use Patchlevel\EventSourcing\Container\ServiceNotFound;
use Psr\Container\ContainerInterface;

use function array_key_exists;

final class ArrayContainer implements ContainerInterface
{
    /** @var list<string> */
    public array $resolvedIds = [];

    /** @param array<string, mixed> $entries */
    public function __construct(
        private readonly array $entries = [],
    ) {
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new ServiceNotFound($id);
        }

        $this->resolvedIds[] = $id;

        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}
