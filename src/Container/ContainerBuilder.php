<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Projection\ProjectionListener;
use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Processor\SendEmailProcessor;

class ContainerBuilder
{
    private const DOCTRINE_STORE_TYPE_SINGLE_TABLE = 'single';
    private const DOCTRINE_STORE_TYPE_MULTIPLE_TABLE = 'multi';

    private ?Connection $connection;
    private ?string $storeType = null;
    private array $aggregates = [];
    private array $projections = [];
    private array $listeners = [];

    /**
     * @param class-string<AggregateRoot> $class
     * @param string $name
     * @return $this
     */
    public function addAggregate(string $class, string $name): self
    {
        $this->aggregates[$class] = $name;

        return $this;
    }

    public function setSingleTableStores(Connection $connection): self
    {
        $this->connection = $connection;
        $this->storeType = self::DOCTRINE_STORE_TYPE_SINGLE_TABLE;

        return $this;
    }

    public function setMultiTableStores(Connection $connection): self
    {
        $this->connection = $connection;
        $this->storeType = self::DOCTRINE_STORE_TYPE_MULTIPLE_TABLE;

        return $this;
    }

    public function addProjection(Projection $projection): self
    {
        $this->projections[] = $projection;

        return $this;
    }

    public function addListener(Listener $listener): self
    {
        $this->listeners[] = $listener;

        return $this;
    }

    public function build(): Container
    {
        if ($this->storeType === self::DOCTRINE_STORE_TYPE_MULTIPLE_TABLE) {
            $store = new MultiTableStore($this->connection, $this->aggregates);
        } elseif ($this->storeType === self::DOCTRINE_STORE_TYPE_SINGLE_TABLE) {
            $store = new SingleTableStore($this->connection, $this->aggregates);
        } else {
            throw new \RuntimeException();
        }

        $listeners = $this->listeners;
        $listeners[] = new ProjectionListener(
            new ProjectionRepository($this->projections)
        );

        $eventStream = new DefaultEventBus();
        $eventStream->addListener(new ProjectionListener($projectionRepository));
        $eventStream->addListener(new SendEmailProcessor());

        return new Container(
            $store
        );
    }
}
