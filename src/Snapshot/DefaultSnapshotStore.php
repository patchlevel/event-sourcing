<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataAwareMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotAdapter;
use Patchlevel\Hydrator\Cryptography\PayloadCryptographer;
use Patchlevel\Hydrator\Hydrator;
use Patchlevel\Hydrator\MetadataHydrator;
use Throwable;

use function array_key_exists;
use function is_array;
use function sprintf;

final class DefaultSnapshotStore implements SnapshotStore
{
    private AdapterRepository $adapterRepository;

    private Hydrator $hydrator;

    private AggregateRootMetadataFactory $metadataFactory;

    /** @param array<string, SnapshotAdapter>|AdapterRepository $adapterRepository */
    public function __construct(
        array|AdapterRepository $adapterRepository,
        Hydrator|null $hydrator = null,
        AggregateRootMetadataFactory|null $metadataFactory = null,
    ) {
        if (is_array($adapterRepository)) {
            $this->adapterRepository = new ArrayAdapterRepository($adapterRepository);
        } else {
            $this->adapterRepository = $adapterRepository;
        }

        $this->hydrator = $hydrator ?? new MetadataHydrator();
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
    public function load(string $aggregateClass, AggregateRootId $id): AggregateRoot
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

    /** @param class-string<AggregateRoot> $aggregateClass */
    public function adapter(string $aggregateClass): SnapshotAdapter
    {
        $adapterName = $this->metadataFactory->metadata($aggregateClass)->snapshot?->store;

        if ($adapterName === null) {
            throw new SnapshotNotConfigured($aggregateClass);
        }

        return $this->adapterRepository->get($adapterName);
    }

    /** @param class-string<AggregateRoot> $aggregateClass */
    private function key(string $aggregateClass, AggregateRootId $aggregateId): string
    {
        $aggregateName = $this->metadataFactory->metadata($aggregateClass)->name;

        return sprintf('%s-%s', $aggregateName, $aggregateId->toString());
    }

    /** @param class-string<AggregateRoot> $aggregateClass */
    private function version(string $aggregateClass): string|null
    {
        return $this->metadataFactory->metadata($aggregateClass)->snapshot?->version;
    }

    /** @param array<string, SnapshotAdapter> $snapshotAdapters */
    public static function createDefault(array $snapshotAdapters, PayloadCryptographer|null $cryptographer = null): self
    {
        return new self(
            new ArrayAdapterRepository($snapshotAdapters),
            new MetadataHydrator(cryptographer: $cryptographer),
        );
    }
}
