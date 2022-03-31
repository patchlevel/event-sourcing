<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Generator;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\SerializedData;
use Patchlevel\EventSourcing\Serializer\Serializer;
use Traversable;

use function array_flip;
use function array_key_exists;
use function array_map;
use function is_int;
use function is_string;

final class MultiTableStore extends DoctrineStore implements PipelineStore
{
    private Serializer $serializer;
    /** @var array<class-string<AggregateRoot>, string> */
    private array $aggregates;
    private string $metadataTableName;

    /**
     * @param array<class-string<AggregateRoot>, string> $aggregates
     */
    public function __construct(
        Connection $eventConnection,
        Serializer $serializer,
        array $aggregates,
        string $metadataTableName = 'eventstore_metadata',
    ) {
        parent::__construct($eventConnection);

        $this->serializer = $serializer;
        $this->aggregates = $aggregates;
        $this->metadataTableName = $metadataTableName;
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     *
     * @return list<Message>
     */
    public function load(string $aggregate, string $id, int $fromPlayhead = 0): array
    {
        $tableName = $this->tableName($aggregate);

        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($tableName)
            ->where('aggregate_id = :id AND playhead > :playhead')
            ->getSQL();

        /** @var list<array{aggregate_id: string, playhead: string|int, event: string, payload: string, recorded_on: string}> $result */
        $result = $this->connection->fetchAllAssociative(
            $sql,
            [
                'id' => $id,
                'playhead' => $fromPlayhead,
            ]
        );

        $platform = $this->connection->getDatabasePlatform();

        return array_map(
            function (array $data) use ($platform, $aggregate): Message {
                return new Message(
                    $aggregate,
                    $data['aggregate_id'],
                    self::normalizePlayhead($data['playhead'], $platform),
                    $this->serializer->deserialize(new SerializedData($data['event'], $data['payload'])),
                    self::normalizeRecordedOn($data['recorded_on'], $platform)
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
            ->where('aggregate_id = :id')
            ->setMaxResults(1)
            ->getSQL();

        $result = $this->connection->fetchOne(
            $sql,
            ['id' => $id]
        );

        if (!is_int($result) && !is_string($result)) {
            throw new WrongQueryResult();
        }

        return ((int)$result) > 0;
    }

    public function save(Message ...$messages): void
    {
        $this->connection->transactional(
            function (Connection $connection) use ($messages): void {
                foreach ($messages as $message) {
                    $event = $message->event();
                    $aggregateName = $this->tableName($message->aggregateClass());

                    $connection->insert(
                        $this->metadataTableName,
                        [
                            'aggregate' => $aggregateName,
                            'aggregate_id' => $message->aggregateId(),
                            'playhead' => $message->playhead(),
                        ],
                    );

                    $data = $this->serializer->serialize($event);

                    $connection->insert(
                        $aggregateName,
                        [
                            'id' => (int)$connection->lastInsertId(),
                            'aggregate_id' => $message->aggregateId(),
                            'playhead' => $message->playhead(),
                            'event' => $data->name,
                            'payload' => $data->payload,
                            'recorded_on' => $message->recordedOn(),
                        ],
                        [
                            'recorded_on' => Types::DATETIMETZ_IMMUTABLE,
                        ]
                    );
                }
            }
        );
    }

    public function stream(int $fromIndex = 0): Generator
    {
        $queries = [];

        foreach ($this->aggregates as $aggregate) {
            $sql = $this->connection->createQueryBuilder()
                ->select('*')
                ->from($aggregate)
                ->where('id > :index')
                ->orderBy('id')
                ->getSQL();

            /**
             * @var Traversable<array{id: string, aggregate_id: string, playhead: string, event: string, payload: string, recorded_on: string}> $query
             */
            $query = $this->connection->iterateAssociative($sql, ['index' => $fromIndex]);

            if (!$query instanceof Generator) {
                throw new WrongQueryResult();
            }

            $queries[$aggregate] = $query;
        }

        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->metadataTableName)
            ->where('id > :index')
            ->orderBy('id')
            ->getSQL();

        /**
         * @var Traversable<array{id: string, aggregate_id: string, playhead: string, aggregate: string}> $metaQuery
         */
        $metaQuery = $this->connection->iterateAssociative($sql, ['index' => $fromIndex]);

        $platform = $this->connection->getDatabasePlatform();
        $classMap = array_flip($this->aggregates);

        foreach ($metaQuery as $metaData) {
            $name = $metaData['aggregate'];

            if (!array_key_exists($name, $classMap)) {
                throw new AggregateNotDefined($name);
            }

            /** @var array{id: string, aggregate_id: string, playhead: string, event: string, payload: string, recorded_on: string}|null $eventData */
            $eventData = $queries[$name]->current();

            if ($eventData === null) {
                throw CorruptedMetadata::fromMissingEntry($metaData['id']);
            }

            $queries[$name]->next();

            if ($eventData['id'] !== $metaData['id']) {
                throw CorruptedMetadata::fromEntryMismatch($metaData['id'], $eventData['id']);
            }

            yield new Message(
                $classMap[$name],
                $eventData['aggregate_id'],
                self::normalizePlayhead($eventData['playhead'], $platform),
                $this->serializer->deserialize(new SerializedData($eventData['event'], $eventData['payload'])),
                self::normalizeRecordedOn($eventData['recorded_on'], $platform)
            );
        }
    }

    public function count(int $fromIndex = 0): int
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->metadataTableName)
            ->where('id > :index')
            ->getSQL();

        $result = $this->connection->fetchOne($sql, ['index' => $fromIndex]);

        if (!is_int($result) && !is_string($result)) {
            throw new WrongQueryResult();
        }

        return (int)$result;
    }

    public function schema(): Schema
    {
        $schema = new Schema([], [], $this->connection->createSchemaManager()->createSchemaConfig());

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
        $table->addColumn('aggregate_id', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('playhead', Types::INTEGER)
            ->setNotnull(true);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['aggregate', 'aggregate_id', 'playhead']);
    }

    private function addAggregateTableToSchema(Schema $schema, string $tableName): void
    {
        $table = $schema->createTable($tableName);

        $table->addColumn('id', Types::BIGINT)
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

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['aggregate_id', 'playhead']);
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
