<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataAwareMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotAdapter;
use Patchlevel\Hydrator\Hydrator\Hydrator;
use Patchlevel\Hydrator\Hydrator\MetadataHydrator;
use Patchlevel\Hydrator\Metadata\AttributeMetadataFactory;
use Throwable;

use function array_key_exists;
use function sprintf;

final class DefaultSnapshotStore implements SnapshotStore
{
    /** @var array<string, SnapshotAdapter> */
    private array $snapshotAdapters;

    private Hydrator $hydrator;

    private AggregateRootMetadataFactory $metadataFactory;

    /**
     * @param array<string, SnapshotAdapter> $snapshotAdapters
     */
    public function __construct(
        array $snapshotAdapters,
        ?Hydrator $hydrator = null,
        ?AggregateRootMetadataFactory $metadataFactory = null
    ) {
        $this->snapshotAdapters = $snapshotAdapters;
        $this->hydrator = $hydrator ?? new MetadataHydrator(new AttributeMetadataFactory());
        $this->metadataFactory = $metadataFactory ?? new AggregateRootMetadataAwareMetadataFactory();
    }

    public function save(AggregateRoot $aggregateRoot): void
    {
        $aggregateClass = $aggregateRoot::class;
        $key = $this->key($aggregateClass, $aggregateRoot->aggregateRootId());

        $adapter = $this->adapter($aggregateClass);

        $data = [
            'version' => $this->version($aggregateClass),
            'payload' => $this->hydrator->extract($aggregateRoot),
        ];

        $adapter->save($key, $data);
    }

    /**
     * @param class-string<T> $aggregateClass
     *
     * @return T
     *
     * @throws SnapshotNotFound
     *
     * @template T of AggregateRoot
     */
    public function load(string $aggregateClass, string $id): AggregateRoot
    {
        $adapter = $this->adapter($aggregateClass);
        $key = $this->key($aggregateClass, $id);

        try {
            $data = $adapter->load($key);
        } catch (Throwable $exception) {
            throw new SnapshotNotFound($aggregateClass, $id, $exception);
        }

        if (!array_key_exists('version', $data) && !array_key_exists('payload', $data)) {
            $data = [
                'version' => null,
                'payload' => $data,
            ];
        }

        if ($this->version($aggregateClass) !== $data['version']) {
            throw new SnapshotVersionInvalid($key);
        }

        return $this->hydrator->hydrate($aggregateClass, $data['payload']);
    }

    /**
     * @param class-string<AggregateRoot> $aggregateClass
     */
    public function adapter(string $aggregateClass): SnapshotAdapter
    {
        $adapterName = $this->metadataFactory->metadata($aggregateClass)->snapshotStore;

        if (!$adapterName) {
            throw new SnapshotNotConfigured($aggregateClass);
        }

        if (!array_key_exists($adapterName, $this->snapshotAdapters)) {
            throw new AdapterNotFound($adapterName);
        }

        return $this->snapshotAdapters[$adapterName];
    }

    /**
     * @param class-string<AggregateRoot> $aggregateClass
     */
    private function key(string $aggregateClass, string $aggregateId): string
    {
        $aggregateName = $this->metadataFactory->metadata($aggregateClass)->name;

        return sprintf('%s-%s', $aggregateName, $aggregateId);
    }

    /**
     * @param class-string<AggregateRoot> $aggregateClass
     */
    private function version(string $aggregateClass): ?string
    {
        return $this->metadataFactory->metadata($aggregateClass)->snapshotVersion;
    }
}
