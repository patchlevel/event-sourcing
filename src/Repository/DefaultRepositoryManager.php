<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\EventBus\Decorator\MessageDecorator;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootClassNotRegistered;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataAwareMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\Store;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function array_key_exists;

final class DefaultRepositoryManager implements RepositoryManager
{
    private ClockInterface $clock;
    private AggregateRootMetadataFactory $metadataFactory;
    private LoggerInterface $logger;

    /** @var array<class-string<AggregateRoot>, Repository> */
    private array $instances = [];

    public function __construct(
        private AggregateRootRegistry $aggregateRootRegistry,
        private Store $store,
        private EventBus $eventBus,
        private SnapshotStore|null $snapshotStore = null,
        private MessageDecorator|null $messageDecorator = null,
        ClockInterface|null $clock = null,
        AggregateRootMetadataFactory|null $metadataFactory = null,
        LoggerInterface|null $logger = null,
    ) {
        $this->metadataFactory = $metadataFactory ?? new AggregateRootMetadataAwareMetadataFactory();
        $this->clock = $clock ?? new SystemClock();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param class-string<T> $aggregateClass
     *
     * @return Repository<T>
     *
     * @template T of AggregateRoot
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
            $this->metadataFactory->metadata($aggregateClass),
            $this->snapshotStore,
            $this->messageDecorator,
            $this->clock,
            $this->logger,
        );
    }
}
