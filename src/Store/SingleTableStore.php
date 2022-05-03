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
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Serializer\SerializedEvent;

use function array_map;
use function is_int;
use function is_string;

final class SingleTableStore extends DoctrineStore implements PipelineStore
{
    private string $storeTableName;

    public function __construct(
        Connection $connection,
        EventSerializer $serializer,
        AggregateRootRegistry $aggregateRootRegistry,
        string $storeTableName = 'eventstore',
    ) {
        parent::__construct($connection, $serializer, $aggregateRootRegistry);

        $this->storeTableName = $storeTableName;
    }

    /**
     * @param class-string<AggregateRoot> $aggregate
     *
     * @return list<Message>
     */
    public function load(string $aggregate, string $id, int $fromPlayhead = 0): array
    {
        $shortName = $this->aggregateRootRegistry->aggregateName($aggregate);

        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->storeTableName)
            ->where('aggregate = :aggregate AND aggregate_id = :id AND playhead > :playhead')
            ->getSQL();

        /** @var list<array{aggregate_id: string, playhead: string|int, event: string, payload: string, recorded_on: string}> $result */
        $result = $this->connection->fetchAllAssociative(
            $sql,
            [
                'aggregate' => $shortName,
                'id' => $id,
                'playhead' => $fromPlayhead,
            ]
        );

        $platform = $this->connection->getDatabasePlatform();

        return array_map(
            function (array $data) use ($platform, $aggregate) {
                return new Message(
                    $aggregate,
                    $data['aggregate_id'],
                    self::normalizePlayhead($data['playhead'], $platform),
                    $this->serializer->deserialize(new SerializedEvent($data['event'], $data['payload'])),
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
        $shortName = $this->aggregateRootRegistry->aggregateName($aggregate);

        $sql = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->storeTableName)
            ->where('aggregate = :aggregate AND aggregate_id = :id')
            ->setMaxResults(1)
            ->getSQL();

        $result = $this->connection->fetchOne(
            $sql,
            [
                'aggregate' => $shortName,
                'id' => $id,
            ]
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

                    $data = $this->serializer->serialize($event);

                    $connection->insert(
                        $this->storeTableName,
                        [
                            'aggregate' => $this->aggregateRootRegistry->aggregateName($message->aggregateClass()),
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

    /**
     * @return Generator<Message>
     */
    public function stream(int $fromIndex = 0): Generator
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->storeTableName)
            ->where('id > :index')
            ->orderBy('id')
            ->getSQL();

        /**
         * @var array<array{
         *     id: string,
         *     aggregate_id: string,
         *     aggregate: string,
         *     playhead: string,
         *     event: string,
         *     payload: string,
         *     recorded_on: string
         * }> $result
         */
        $result = $this->connection->iterateAssociative($sql, ['index' => $fromIndex]);
        $platform = $this->connection->getDatabasePlatform();

        foreach ($result as $data) {
            yield new Message(
                $this->aggregateRootRegistry->aggregateClass($data['aggregate']),
                $data['aggregate_id'],
                self::normalizePlayhead($data['playhead'], $platform),
                $this->serializer->deserialize(new SerializedEvent($data['event'], $data['payload'])),
                self::normalizeRecordedOn($data['recorded_on'], $platform)
            );
        }
    }

    public function count(int $fromIndex = 0): int
    {
        $sql = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->storeTableName)
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

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['aggregate', 'aggregate_id', 'playhead']);

        $this->addOutboxSchema($schema);

        return $schema;
    }
}
