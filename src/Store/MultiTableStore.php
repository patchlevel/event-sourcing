<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Generator;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Schema\SchemaConfigurator;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use Traversable;

use function array_key_exists;
use function array_map;
use function is_int;
use function is_string;
use function sprintf;

final class MultiTableStore extends DoctrineStore implements StreamableStore, SchemaConfigurator
{
    public function __construct(
        Connection $connection,
        EventSerializer $serializer,
        AggregateRootRegistry $aggregateRootRegistry,
        private string $metadataTableName = 'eventstore_metadata',
    ) {
        parent::__construct($connection, $serializer, $aggregateRootRegistry);
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     *
     * @return list<Message>
     */
    public function load(string $aggregate, string $id, int $fromPlayhead = 0): array
    {
        $tableName = $this->aggregateRootRegistry->aggregateName($aggregate);

        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($tableName)
            ->where('aggregate_id = :id')
            ->andWhere('playhead > :playhead')
            ->andWhere('archived = :archived')
            ->getSQL();

        /** @var list<array{aggregate_id: string, playhead: string|int, event: string, payload: string, recorded_on: string, custom_headers: string}> $result */
        $result = $this->connection->fetchAllAssociative(
            $sql,
            [
                'id' => $id,
                'playhead' => $fromPlayhead,
                'archived' => false,
            ],
            [
                'archived' => Types::BOOLEAN,
            ],
        );

        $platform = $this->connection->getDatabasePlatform();

        return array_map(
            function (array $data) use ($platform, $aggregate): Message {
                $event = $this->serializer->deserialize(new SerializedEvent($data['event'], $data['payload']));

                return Message::create($event)
                    ->withAggregateClass($aggregate)
                    ->withAggregateId($data['aggregate_id'])
                    ->withPlayhead(self::normalizePlayhead($data['playhead'], $platform))
                    ->withRecordedOn(self::normalizeRecordedOn($data['recorded_on'], $platform))
                    ->withCustomHeaders(self::normalizeCustomHeaders($data['custom_headers'], $platform));
            },
            $result,
        );
    }

    /** @param class-string<AggregateRoot> $aggregate */
    public function archiveMessages(string $aggregate, string $id, int $untilPlayhead): void
    {
        $tableName = $this->aggregateRootRegistry->aggregateName($aggregate);

        $statement = $this->connection->prepare(sprintf(
            'UPDATE %s 
            SET archived = true
            WHERE aggregate_id = :aggregate_id
            AND playhead < :playhead
            AND archived = false',
            $tableName,
        ));
        $statement->bindValue('aggregate_id', $id);
        $statement->bindValue('playhead', $untilPlayhead);

        $statement->executeQuery();
    }

    /** @param class-string<AggregateRoot> $aggregate */
    public function has(string $aggregate, string $id): bool
    {
        $tableName = $this->aggregateRootRegistry->aggregateName($aggregate);

        $sql = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($tableName)
            ->where('aggregate_id = :id')
            ->setMaxResults(1)
            ->getSQL();

        $result = $this->connection->fetchOne(
            $sql,
            ['id' => $id],
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

                    $aggregateName = $this->aggregateRootRegistry->aggregateName($message->aggregateClass());
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

    public function stream(int $fromIndex = 0): Generator
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->metadataTableName)
            ->where('id > :index')
            ->orderBy('id')
            ->getSQL();

        /** @var Traversable<array{id: string, aggregate_id: string, playhead: string, aggregate: string}> $metaQuery */
        $metaQuery = $this->connection->iterateAssociative($sql, ['index' => $fromIndex]);

        $platform = $this->connection->getDatabasePlatform();

        $queries = [];

        foreach ($metaQuery as $metaData) {
            $aggregateName = $metaData['aggregate'];

            if (!array_key_exists($aggregateName, $queries)) {
                $sql = $this->connection->createQueryBuilder()
                    ->select('*')
                    ->from($aggregateName)
                    ->where('id > :index')
                    ->orderBy('id')
                    ->getSQL();

                /** @var Traversable<array{id: string, aggregate_id: string, playhead: string, event: string, payload: string, recorded_on: string, custom_headers: string}> $query */
                $query = $this->connection->iterateAssociative($sql, ['index' => $fromIndex]);

                if (!$query instanceof Generator) {
                    throw new WrongQueryResult();
                }

                $queries[$aggregateName] = $query;
            }

            /** @var array{id: string, aggregate_id: string, playhead: string, event: string, payload: string, recorded_on: string, custom_headers: string}|null $eventData */
            $eventData = $queries[$aggregateName]->current();

            if ($eventData === null) {
                throw CorruptedMetadata::fromMissingEntry($metaData['aggregate_id']);
            }

            $queries[$aggregateName]->next();

            if ($eventData['id'] !== $metaData['id']) {
                throw CorruptedMetadata::fromEntryMismatch($metaData['aggregate_id'], $eventData['aggregate_id']);
            }

            $event = $this->serializer->deserialize(new SerializedEvent($eventData['event'], $eventData['payload']));

            yield Message::create($event)
                ->withAggregateClass($this->aggregateRootRegistry->aggregateClass($aggregateName))
                ->withAggregateId($eventData['aggregate_id'])
                ->withPlayhead(self::normalizePlayhead($eventData['playhead'], $platform))
                ->withRecordedOn(self::normalizeRecordedOn($eventData['recorded_on'], $platform))
                ->withCustomHeaders(self::normalizeCustomHeaders($eventData['custom_headers'], $platform));
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

    public function configureSchema(Schema $schema, Connection $connection): void
    {
        if ($this->connection !== $connection) {
            return;
        }

        $this->addMetaTableToSchema($schema);

        foreach ($this->aggregateRootRegistry->aggregateNames() as $tableName) {
            $this->addAggregateTableToSchema($schema, $tableName);
        }

        $this->addOutboxSchema($schema);
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
        $table->addColumn('new_stream_start', Types::BOOLEAN)
            ->setNotnull(true)
            ->setDefault(false);
        $table->addColumn('archived', Types::BOOLEAN)
            ->setNotnull(true)
            ->setDefault(false);
        $table->addColumn('custom_headers', Types::JSON)
            ->setNotnull(true);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['aggregate_id', 'playhead']);
        $table->addIndex(['aggregate_id', 'playhead', 'archived']);
    }
}
