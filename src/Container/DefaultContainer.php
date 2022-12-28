<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Container\Factory\AggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Container\Factory\ConnectionFactory;
use Patchlevel\EventSourcing\Container\Factory\EventBusFactory;
use Patchlevel\EventSourcing\Container\Factory\EventSerializerFactory;
use Patchlevel\EventSourcing\Container\Factory\RepositoryManagerFactory;
use Patchlevel\EventSourcing\Container\Factory\SchemaDirectorFactory;
use Patchlevel\EventSourcing\Container\Factory\StoreFactory;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorRepository;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use Patchlevel\EventSourcing\Store\Store;
use Psr\Container\ContainerInterface;

use function array_key_exists;
use function array_merge;
use function is_callable;

/**
 * @psalm-type Config = array{
 *     event_bus: ?array{type: string, service: string},
 *     projection: array{projectionist: bool},
 *     watch_server: array{enabled: bool, host: string},
 *     connection: ?array{service: ?string, url: ?string},
 *     store: array{type: string, merge_orm_schema: bool},
 *     aggregates: list<string>,
 *     events: list<string>,
 *     snapshot_stores: array<string, array{type: string, service: string}>,
 *     migration: array{path: string, namespace: string},
 *     clock: array{freeze: ?string, service: ?string}
 * }
 */
final class DefaultContainer implements ContainerInterface
{
    /** @var array<string, mixed> */
    private array $definitions;

    /** @var array<string, mixed> */
    private array $resolvedEntries;

    /**
     * @param Config               $config
     * @param array<string, mixed> $definitions
     */
    public function __construct(array $config = [], array $definitions = [])
    {
        $this->resolvedEntries = [];

        $this->definitions = array_merge(
            [
                'config' => ['event_sourcing' => $config],
                'event_sourcing.connection' => new ConnectionFactory(),
                'event_sourcing.event_bus' => new EventBusFactory(),
                'event_sourcing.event_serializer' => new EventSerializerFactory(),
                'event_sourcing.store' => new StoreFactory(),
                'event_sourcing.aggregate_root_registry' => new AggregateRootRegistryFactory(),
                'event_sourcing.repository_manager' => new RepositoryManagerFactory(),
                'event_sourcing.schema_director' => new SchemaDirectorFactory(),
            ],
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

        if (is_callable($value)) {
            $value = $value($this);
        }

        $this->resolvedEntries[$id] = $value;

        return $value;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->definitions) || array_key_exists($id, $this->resolvedEntries);
    }

    public function connection(): Connection
    {
        return $this->get('event_sourcing.connection');
    }

    public function repositoryManager(): RepositoryManager
    {
        return $this->get('event_sourcing.repository_manager');
    }

    /**
     * @param class-string<T> $aggregateClass
     *
     * @return Repository<T>
     *
     * @template T of AggregateRoot
     */
    public function repository(string $aggregateClass): Repository
    {
        return $this->repositoryManager()->get($aggregateClass);
    }

    public function store(): Store
    {
        return $this->get('event_sourcing.repository.connection');
    }

    public function schemaDirector(): SchemaDirector
    {
        return $this->get('event_sourcing.schema_director');
    }

    public function projectorRepository(): ProjectorRepository
    {
        return $this->get('event_sourcing.projector_repository');
    }
}
