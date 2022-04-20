<?php

namespace Patchlevel\EventSourcing\Container;

use Patchlevel\EventSourcing\Container\Factory\AggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Container\Factory\ConnectionFactory;
use Patchlevel\EventSourcing\Container\Factory\SerializerFactory;
use Patchlevel\EventSourcing\Container\Factory\StoreFactory;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Store\Store;
use Psr\Container\ContainerInterface;

class DefaultContainer implements ContainerInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $definitions;

    /**
     * @var array<string, mixed>
     */
    private array $resolvedEntries;

    /**
     * @param array<string, mixed> $definitions
     */
    public function __construct(array $definitions)
    {
        $this->resolvedEntries = [];
        $this->definitions = array_merge(
            $definitions,
            [ContainerInterface::class => $this]
        );
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new NotFound("No entry or class found for '$id'");
        }

        if (array_key_exists($id, $this->resolvedEntries)) {
            return $this->resolvedEntries[$id];
        }

        $value = $this->definitions[$id];

        if ($value instanceof \Closure) {
            $value = $value($this);
        }

        $this->resolvedEntries[$id] = $value;

        return $value;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->definitions) || array_key_exists($id, $this->resolvedEntries);
    }

    public function repository(string $aggregateName): Repository
    {
        return $this->get('event_sourcing.repository.'.$aggregateName);
    }

    public function store(): Store
    {
        return $this->get('event_sourcing.repository.connection');
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function create(array $config = []): self
    {
        return new DefaultContainer([
            'config' => $config,
            'event_sourcing.connection' => new ConnectionFactory(),
            'event_sourcing.serializer' => new SerializerFactory(),
            'event_sourcing.store' => new StoreFactory(),
            'event_sourcing.aggregate_root_registry' => new AggregateRootRegistryFactory(),
        ]);
    }
}