<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Generator;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Traversable;

use function array_flip;
use function array_key_exists;
use function array_map;

final class MultiTableStore extends DoctrineStore implements PipelineStore
{
    /** @var array<class-string<AggregateRoot>, string> */
    private array $aggregates;
    private string $metadataTableName;

    /**
     * @param array<class-string<AggregateRoot>, string> $aggregates
     */
    public function __construct(
        Connection $eventConnection,
        array $aggregates,
        string $metadataTableName = 'eventstore_metadata'
    ) {
        parent::__construct($eventConnection);

        $this->aggregates = $aggregates;
        $this->metadataTableName = $metadataTableName;
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     *
     * @return array<AggregateChanged>
     */
    public function load(string $aggregate, string $id, int $fromPlayhead = -1): array
    {
        $tableName = $this->tableName($aggregate);

        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($tableName)
            ->where('aggregateId = :id AND playhead > :playhead')
            ->getSQL();

        /** @var array<array{aggregateId: string, playhead: string, event: class-string<AggregateChanged>, payload: string, recordedOn: string}> $result */
        $result = $this->connection->fetchAllAssociative(
            $sql,
            [
                'id' => $id,
                'playhead' => $fromPlayhead,
            ]
        );

        $platform = $this->connection->getDatabasePlatform();

        return array_map(
            static function (array $data) use ($platform): AggregateChanged {
                return AggregateChanged::deserialize(
                    self::normalizeResult($platform, $data)
                );
            },
            $result
        );
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     */
    public function has(string $aggregate, string $id): bool
    {
        $tableName = $this->tableName($aggregate);

        $sql = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($tableName)
            ->where('aggregateId = :id')
            ->setMaxResults(1)
            ->getSQL();

        $result = (int)$this->connection->fetchOne(
            $sql,
            ['id' => $id]
        );

        return $result > 0;
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     * @param array<AggregateChanged>     $events
     */
    public function saveBatch(string $aggregate, string $id, array $events): void
    {
        $tableName = $this->tableName($aggregate);

        $this->connection->transactional(
            function (Connection $connection) use ($tableName, $id, $events): void {
                foreach ($events as $event) {
                    if ($event->aggregateId() !== $id) {
                        throw new StoreException('id missmatch');
                    }

                    $this->saveEvent(
                        $connection,
                        $tableName,
                        $event
                    );
                }
            }
        );
    }

    public function all(): Generator
    {
        $queries = [];

        foreach ($this->aggregates as $aggregate) {
            $sql = $this->connection->createQueryBuilder()
                ->select('*')
                ->from($aggregate)
                ->orderBy('id')
                ->getSQL();

            /** @var Traversable<array{aggregateId: string, playhead: string, event: class-string<AggregateChanged>, payload: string, recordedOn: string}> $query */
            $query = $this->connection->iterateAssociative($sql);

            if (!$query instanceof Generator) {
                throw new StoreException();
            }

            $queries[$aggregate] = $query;
        }

        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->metadataTableName)
            ->orderBy('id')
            ->getSQL();

        /** @var Traversable<array{aggregateId: string, playhead: string, aggregate: class-string<AggregateChanged>}> $metaQuery */
        $metaQuery = $this->connection->iterateAssociative($sql);

        $platform = $this->connection->getDatabasePlatform();
        $classMap = array_flip($this->aggregates);

        foreach ($metaQuery as $metaData) {
            $name = $metaData['aggregate'];

            if (!array_key_exists($name, $classMap)) {
                throw new StoreException();
            }

            $eventData = $queries[$name]->current();
            $queries[$name]->next();

            if (
                $eventData['aggregateId'] !== $metaData['aggregateId']
                || $eventData['playhead'] !== $metaData['playhead']
            ) {
                throw new CorruptedMetadata(
                    $metaData['aggregateId'],
                    $metaData['playhead'],
                    $eventData['aggregateId'],
                    $eventData['playhead']
                );
            }

            yield new EventBucket(
                $classMap[$name],
                AggregateChanged::deserialize(
                    self::normalizeResult($platform, $eventData)
                )
            );
        }
    }

    public function count(): int
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->metadataTableName)
            ->getSQL();

        return (int)$this->connection->fetchOne($sql);
    }

    public function saveEventBucket(EventBucket $bucket): void
    {
        $this->saveEvent(
            $this->connection,
            $this->tableName($bucket->aggregateClass()),
            $bucket->event()
        );
    }

    private function saveEvent(Connection $connection, string $aggregate, AggregateChanged $event): void
    {
        $data = $event->serialize();

        $connection->insert(
            $aggregate,
            $data,
            [
                'recordedOn' => Types::DATETIMETZ_IMMUTABLE,
            ]
        );

        $connection->insert(
            $this->metadataTableName,
            [
                'aggregate' => $aggregate,
                'aggregateId' => $data['aggregateId'],
                'playhead' => $data['playhead'],
            ],
        );
    }

    public function schema(): Schema
    {
        $schema = new Schema([], [], $this->connection->getSchemaManager()->createSchemaConfig());

        $this->addMetaTableToSchema($schema);

        foreach ($this->aggregates as $tableName) {
            $this->addAggregateTableToSchema($schema, $tableName);
        }

        return $schema;
    }

    private function addMetaTableToSchema(Schema $schema): void
    {
        $table = $schema->createTable($this->metadataTableName);

        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true);
        $table->addColumn('aggregate', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('aggregateId', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('playhead', Types::INTEGER)
            ->setNotnull(true);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['aggregate', 'aggregateId', 'playhead']);
    }

    private function addAggregateTableToSchema(Schema $schema, string $tableName): void
    {
        $table = $schema->createTable($tableName);

        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true);
        $table->addColumn('aggregateId', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('playhead', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('event', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('payload', Types::JSON)
            ->setNotnull(true);
        $table->addColumn('recordedOn', Types::DATETIMETZ_IMMUTABLE)
            ->setNotnull(false);

        $table->setPrimaryKey(['id']);

        $table->addUniqueIndex(['aggregateId', 'playhead']);
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     */
    private function tableName(string $aggregate): string
    {
        if (!array_key_exists($aggregate, $this->aggregates)) {
            throw new AggregateNotDefined($aggregate);
        }

        return $this->aggregates[$aggregate];
    }
}
