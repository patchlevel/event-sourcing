<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Schema\SchemaConfigurator;
use Patchlevel\EventSourcing\Serializer\EventSerializer;

use function is_int;
use function is_string;
use function sprintf;

final class DoctrineDbalStore implements Store, ArchivableStore, SchemaConfigurator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EventSerializer $serializer,
        private readonly AggregateRootRegistry $aggregateRootRegistry,
        private readonly string $storeTableName = 'eventstore',
    ) {
    }

    public function load(Criteria|null $criteria = null): DoctrineDbalStoreStream
    {
        $builder = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->storeTableName)
            ->orderBy('id');

        $this->addWhere($builder, $criteria ?? new Criteria());

        return new DoctrineDbalStoreStream(
            $this->connection->executeQuery(
                $builder->getSQL(),
                $builder->getParameters(),
                $builder->getParameterTypes(),
            ),
            $this->serializer,
            $this->aggregateRootRegistry,
            $this->connection->getDatabasePlatform(),
        );
    }

    public function count(Criteria|null $criteria = null): int
    {
        $builder = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->storeTableName);

        $this->addWhere($builder, $criteria ?? new Criteria());

        $result = $this->connection->fetchOne(
            $builder->getSQL(),
            $builder->getParameters(),
            $builder->getParameterTypes(),
        );

        if (!is_int($result) && !is_string($result)) {
            throw new WrongQueryResult();
        }

        return (int)$result;
    }

    private function addWhere(QueryBuilder $builder, Criteria $criteria): void
    {
        if ($criteria->aggregateClass !== null) {
            $shortName = $this->aggregateRootRegistry->aggregateName($criteria->aggregateClass);
            $builder->andWhere('aggregate = :aggregate');
            $builder->setParameter('aggregate', $shortName);
        }

        if ($criteria->aggregateId !== null) {
            $builder->andWhere('aggregate_id = :id');
            $builder->setParameter('id', $criteria->aggregateId);
        }

        if ($criteria->fromPlayhead !== null) {
            $builder->andWhere('playhead > :playhead');
            $builder->setParameter('playhead', $criteria->fromPlayhead, Types::INTEGER);
        }

        if ($criteria->archived !== null) {
            $builder->andWhere('archived = :archived');
            $builder->setParameter('archived', $criteria->archived, Types::BOOLEAN);
        }

        if ($criteria->fromIndex === null) {
            return;
        }

        $builder->andWhere('id > :index');
        $builder->setParameter('index', $criteria->fromIndex, Types::INTEGER);
    }

    public function save(Message ...$messages): void
    {
        $this->connection->transactional(
            function (Connection $connection) use ($messages): void {
                foreach ($messages as $message) {
                    $data = $this->serializer->serialize($message->event());

                    $connection->insert(
                        $this->storeTableName,
                        [
                            'aggregate' => $this->aggregateRootRegistry->aggregateName($message->aggregateClass()),
                            'aggregate_id' => $message->aggregateId(),
                            'playhead' => $message->playhead(),
                            'event' => $data->name,
                            'payload' => $data->payload,
                            'recorded_on' => $message->recordedOn(),
                            'new_stream_start' => $message->newStreamStart(),
                            'archived' => $message->archived(),
                            'custom_headers' => $message->customHeaders(),
                        ],
                        [
                            'recorded_on' => Types::DATETIMETZ_IMMUTABLE,
                            'custom_headers' => Types::JSON,
                            'new_stream_start' => Types::BOOLEAN,
                            'archived' => Types::BOOLEAN,
                        ],
                    );
                }
            },
        );
    }

    /**
     * @param Closure():ClosureReturn $function
     *
     * @template ClosureReturn
     */
    public function transactional(Closure $function): void
    {
        $this->connection->transactional($function);
    }

    /** @param class-string<AggregateRoot> $aggregate */
    public function archiveMessages(string $aggregate, string $id, int $untilPlayhead): void
    {
        $aggregateName = $this->aggregateRootRegistry->aggregateName($aggregate);

        $statement = $this->connection->prepare(sprintf(
            'UPDATE %s 
            SET archived = true
            WHERE aggregate = :aggregate
            AND aggregate_id = :aggregate_id
            AND playhead < :playhead
            AND archived = false',
            $this->storeTableName,
        ));
        $statement->bindValue('aggregate', $aggregateName);
        $statement->bindValue('aggregate_id', $id);
        $statement->bindValue('playhead', $untilPlayhead);

        $statement->executeQuery();
    }

    public function configureSchema(Schema $schema, Connection $connection): void
    {
        if ($this->connection !== $connection) {
            return;
        }

        $table = $schema->createTable($this->storeTableName);

        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true);
        $table->addColumn('aggregate', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('aggregate_id', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('playhead', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('event', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('payload', Types::JSON)
            ->setNotnull(true);
        $table->addColumn('recorded_on', Types::DATETIMETZ_IMMUTABLE)
            ->setNotnull(false);
        $table->addColumn('new_stream_start', Types::BOOLEAN)
            ->setNotnull(true)
            ->setDefault(false);
        $table->addColumn('archived', Types::BOOLEAN)
            ->setNotnull(true)
            ->setDefault(false);
        $table->addColumn('custom_headers', Types::JSON)
            ->setNotnull(true);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['aggregate', 'aggregate_id', 'playhead']);
        $table->addIndex(['aggregate', 'aggregate_id', 'playhead', 'archived']);
    }
}
