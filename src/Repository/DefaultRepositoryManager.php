<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRootInterface;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\EventBus\Decorator\MessageDecorator;
use Patchlevel\EventSourcing\EventBus\Decorator\RecordedOnDecorator;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootClassNotRegistered;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataAwareMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\Store;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function array_key_exists;

final class DefaultRepositoryManager implements RepositoryManager
{
    private AggregateRootRegistry $aggregateRootRegistry;
    private Store $store;
    private EventBus $eventBus;
    private ?SnapshotStore $snapshotStore;
    private MessageDecorator $messageDecorator;
    private LoggerInterface $logger;
    private AggregateRootMetadataFactory $metadataFactory;

    /** @var array<class-string<AggregateRootInterface>, Repository> */
    private array $instances = [];

    public function __construct(
        AggregateRootRegistry $aggregateRootRegistry,
        Store $store,
        EventBus $eventBus,
        ?SnapshotStore $snapshotStore = null,
        ?MessageDecorator $messageDecorator = null,
        ?LoggerInterface $logger = null,
        ?AggregateRootMetadataFactory $metadataFactory = null,
    ) {
        $this->aggregateRootRegistry = $aggregateRootRegistry;
        $this->store = $store;
        $this->eventBus = $eventBus;
        $this->snapshotStore = $snapshotStore;
        $this->messageDecorator = $messageDecorator ?? new RecordedOnDecorator(new SystemClock());
        $this->logger = $logger ?? new NullLogger();
        $this->metadataFactory = $metadataFactory ?? new AggregateRootMetadataAwareMetadataFactory();
    }

    /**
     * @param class-string<T> $aggregateClass
     *
     * @return Repository<T>
     *
     * @template T of AggregateRootInterface
     */
    public function get(string $aggregateClass): Repository
    {
        if (array_key_exists($aggregateClass, $this->instances)) {
            /** @var Repository<T> $repository */
            $repository = $this->instances[$aggregateClass];

            return $repository;
        }

        if (!$this->aggregateRootRegistry->hasAggregateClass($aggregateClass)) {
            throw new AggregateRootClassNotRegistered($aggregateClass);
        }

        return $this->instances[$aggregateClass] = new DefaultRepository(
            $this->store,
            $this->eventBus,
            $aggregateClass,
            $this->snapshotStore,
            $this->messageDecorator,
            $this->logger,
            $this->metadataFactory->metadata($aggregateClass)
        );
    }
}
